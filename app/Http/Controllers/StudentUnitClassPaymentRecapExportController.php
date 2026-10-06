<?php

namespace App\Http\Controllers;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\StudentAcademicEnrollment;
use App\Services\StudentClassPaymentRecapService;
use App\Services\StudentClassPaymentRecapSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentUnitClassPaymentRecapExportController extends Controller
{
    public function __invoke(
        Request $request,
        StudentClassPaymentRecapService $reportService,
        StudentClassPaymentRecapSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'school_level' => ['required', 'string', Rule::enum(SchoolLevel::class)],
        ]);
        $academicYear = AcademicYear::query()->findOrFail((int) $validated['academic_year_id']);
        $schoolLevel = SchoolLevel::from($validated['school_level']);
        $eligibleClassIds = StudentAcademicEnrollment::query()
            ->select('school_class_id')
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->whereNotNull('school_class_id');
        $schoolClasses = SchoolClass::query()
            ->whereIn('level', $schoolLevel->classLevels())
            ->whereIn('id', $eligibleClassIds)
            ->orderBy('level')
            ->orderBy('name')
            ->get(['id', 'name', 'level']);

        if ($schoolClasses->isEmpty()) {
            throw ValidationException::withMessages([
                'school_level' => 'Tidak ada kelas dengan enrollment aktif untuk tahun ajaran dan jenjang yang dipilih.',
            ]);
        }

        $reports = (function () use ($schoolClasses, $reportService, $academicYear, $schoolLevel): iterable {
            foreach ($schoolClasses as $schoolClass) {
                yield $reportService->generate($academicYear->id, $schoolLevel, $schoolClass->id);
            }
        })();
        $path = $spreadsheet->create($reports);
        $filename = sprintf(
            'Laporan_%s_%s.xlsx',
            $spreadsheet->filenameSegment($schoolLevel->value),
            $spreadsheet->filenameSegment(str_replace('/', '-', $academicYear->year)),
        );

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }
}

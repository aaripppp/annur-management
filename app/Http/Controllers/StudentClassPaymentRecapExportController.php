<?php

namespace App\Http\Controllers;

use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use App\Services\StudentClassPaymentRecapService;
use App\Services\StudentClassPaymentRecapSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentClassPaymentRecapExportController extends Controller
{
    public function __invoke(
        Request $request,
        StudentClassPaymentRecapService $reportService,
        StudentClassPaymentRecapSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'school_level' => ['required', 'string', Rule::enum(SchoolLevel::class)],
            'class_id' => ['required', 'integer', 'exists:school_classes,id'],
        ]);
        $schoolLevel = SchoolLevel::from($validated['school_level']);
        $schoolClass = SchoolClass::query()
            ->whereKey((int) $validated['class_id'])
            ->whereIn('level', $schoolLevel->classLevels())
            ->first();

        if ($schoolClass === null) {
            throw ValidationException::withMessages([
                'class_id' => 'Kelas tidak sesuai dengan jenjang yang dipilih.',
            ]);
        }

        $report = $reportService->generate(
            (int) $validated['academic_year_id'],
            $schoolLevel,
            $schoolClass->id,
        );
        $path = $spreadsheet->create([$report]);
        $filename = sprintf(
            'Laporan_Kelas_%s_%s.xlsx',
            $spreadsheet->filenameSegment($report['school_class']),
            $spreadsheet->filenameSegment(str_replace('/', '-', $report['academic_year'])),
        );

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }
}

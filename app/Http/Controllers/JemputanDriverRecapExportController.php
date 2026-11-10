<?php

namespace App\Http\Controllers;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\SchoolClass;
use App\Models\StudentAcademicEnrollment;
use App\Services\JemputanDriverRecapSpreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JemputanDriverRecapExportController extends Controller
{
    public function __invoke(Request $request, JemputanDriverRecapSpreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('viewAny', JemputanDriver::class);

        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in(['all', 'filtered'])],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'driver_id' => ['nullable', 'integer', 'exists:jemputan_drivers,id', 'required_if:mode,filtered'],
            'school_level' => ['nullable', 'string', Rule::enum(SchoolLevel::class)],
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
        ]);
        $academicYear = AcademicYear::query()->findOrFail((int) $validated['academic_year_id']);
        $schoolLevel = filled($validated['school_level'] ?? null)
            ? SchoolLevel::from($validated['school_level'])
            : null;
        $schoolClassId = filled($validated['class_id'] ?? null)
            ? (int) $validated['class_id']
            : null;

        $this->validateHistoricalClass($academicYear, $schoolLevel, $schoolClassId);

        $driversQuery = JemputanDriver::query();

        if ($validated['mode'] === 'filtered') {
            $driversQuery
                ->whereKey((int) $validated['driver_id'])
                ->whereHas('assignments', fn (Builder $query) => $query
                    ->where('academic_year_id', $academicYear->id)
                    ->currentMembership());
        } else {
            $driversQuery->whereHas('assignments', fn (Builder $query) => $query
                ->where('academic_year_id', $academicYear->id)
                ->currentMembership());
        }

        $driverCount = (clone $driversQuery)->count();

        if ($driverCount === 0) {
            throw ValidationException::withMessages([
                'driver_id' => 'Tidak ada kelompok sopir dengan anggota aktif pada tahun ajaran yang dipilih.',
            ]);
        }

        if ($driverCount > JemputanDriverRecapSpreadsheet::MAX_WORKSHEETS) {
            throw ValidationException::withMessages([
                'driver_id' => 'Jumlah kelompok sopir melebihi batas worksheet Excel.',
            ]);
        }

        $drivers = $driversQuery
            ->orderBy('name')
            ->orderBy('id')
            ->lazy(100, ['id', 'name', 'phone', 'is_active']);

        try {
            $path = $spreadsheet->create($academicYear, $drivers, $schoolLevel, $schoolClassId);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'export' => 'Rekap Jemputan gagal dibuat. Silakan coba kembali.',
            ]);
        }

        return response()->download(
            $path,
            $spreadsheet->filename($academicYear),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    private function validateHistoricalClass(
        AcademicYear $academicYear,
        ?SchoolLevel $schoolLevel,
        ?int $schoolClassId,
    ): void {
        if ($schoolClassId === null) {
            return;
        }

        $schoolClass = SchoolClass::query()->find($schoolClassId);
        $hasHistoricalEnrollment = StudentAcademicEnrollment::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClassId)
            ->exists();
        $matchesLevel = $schoolLevel === null
            || ($schoolClass !== null && in_array((int) $schoolClass->level, $schoolLevel->classLevels(), true));

        if (! $hasHistoricalEnrollment || ! $matchesLevel) {
            throw ValidationException::withMessages([
                'class_id' => 'Kelas tidak sesuai dengan tahun ajaran dan jenjang yang dipilih.',
            ]);
        }
    }
}

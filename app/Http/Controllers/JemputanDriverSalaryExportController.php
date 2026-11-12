<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Services\JemputanDriverSalaryService;
use App\Services\JemputanDriverSalarySpreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JemputanDriverSalaryExportController extends Controller
{
    public function __invoke(
        Request $request,
        JemputanDriverSalaryService $salaryService,
        JemputanDriverSalarySpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        Gate::authorize('viewAny', JemputanDriver::class);

        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'driver_id' => ['required', 'integer', 'exists:jemputan_drivers,id'],
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);
        $academicYear = AcademicYear::query()->findOrFail((int) $validated['academic_year_id']);
        $this->validateMonth($academicYear, $validated['month']);
        $report = $salaryService->exportData(
            $academicYear->id,
            (int) $validated['driver_id'],
            $validated['month'],
        );

        if (! $report['summary']['percentage_configured']) {
            throw ValidationException::withMessages([
                'export' => 'Persentase potongan yayasan sopir ini belum diatur. Atur persentase terlebih dahulu di Master Sopir.',
            ]);
        }

        try {
            $path = $spreadsheet->create($report);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'export' => 'Export gaji sopir gagal dibuat. Silakan coba kembali.',
            ]);
        }

        return response()->download(
            $path,
            $spreadsheet->filename($report),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    private function validateMonth(AcademicYear $academicYear, string $month): void
    {
        $start = CarbonImmutable::parse($academicYear->start_date)->startOfMonth();
        $isValid = false;

        for ($offset = 0; $offset < 12; $offset++) {
            if ($start->addMonths($offset)->format('Y-m') === $month) {
                $isValid = true;

                break;
            }
        }

        if (! $isValid) {
            throw ValidationException::withMessages([
                'month' => 'Bulan tidak sesuai dengan tahun ajaran yang dipilih.',
            ]);
        }
    }
}

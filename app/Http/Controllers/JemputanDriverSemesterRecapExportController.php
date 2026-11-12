<?php

namespace App\Http\Controllers;

use App\Enums\SchoolLevel;
use App\Models\JemputanDriver;
use App\Services\JemputanDriverSemesterRecapService;
use App\Services\JemputanDriverSemesterRecapSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JemputanDriverSemesterRecapExportController extends Controller
{
    public function __invoke(
        Request $request,
        JemputanDriverSemesterRecapService $recapService,
        JemputanDriverSemesterRecapSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        Gate::authorize('viewAny', JemputanDriver::class);

        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'school_level' => ['nullable', Rule::enum(SchoolLevel::class)],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
        ]);
        $report = $recapService->generate(
            (int) $validated['academic_year_id'],
            isset($validated['school_level'])
                ? SchoolLevel::from($validated['school_level'])
                : null,
            (int) $validated['semester'],
        );

        try {
            $path = $spreadsheet->create($report);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'export' => 'Export Rekap Jemputan 6 Bulan gagal dibuat. Silakan coba kembali.',
            ]);
        }

        return response()->download(
            $path,
            $spreadsheet->filename($report),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }
}

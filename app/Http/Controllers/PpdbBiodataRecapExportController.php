<?php

namespace App\Http\Controllers;

use App\Enums\SchoolLevel;
use App\Models\ProspectiveStudent;
use App\Services\PpdbBiodataRecapSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class PpdbBiodataRecapExportController extends Controller
{
    public function __invoke(Request $request, PpdbBiodataRecapSpreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('exportBiodataRecap', ProspectiveStudent::class);

        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'school_level' => ['required', 'string', Rule::enum(SchoolLevel::class)],
        ]);

        $schoolLevel = SchoolLevel::from($validated['school_level']);
        $user = $request->user();

        abort_if(
            $user?->isTuUnit() && ($user->school_level === null || $schoolLevel !== $user->school_level),
            403,
        );

        try {
            $export = $spreadsheet->create(
                (int) $validated['academic_year_id'],
                $schoolLevel,
            );
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'export' => 'Rekap biodata PPDB gagal dibuat. Silakan coba kembali.',
            ]);
        }

        try {
            return response()->download(
                $export['path'],
                $export['filename'],
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )->deleteFileAfterSend(true);
        } catch (Throwable) {
            if (is_file($export['path'])) {
                @unlink($export['path']);
            }

            throw ValidationException::withMessages([
                'export' => 'Rekap biodata PPDB gagal disiapkan untuk diunduh.',
            ]);
        }
    }
}

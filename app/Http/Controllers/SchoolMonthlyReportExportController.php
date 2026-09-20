<?php

namespace App\Http\Controllers;

use App\Services\SchoolMonthlyReportService;
use App\Services\SchoolMonthlyReportSpreadsheet;
use App\Support\SchoolReportLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolMonthlyReportExportController extends Controller
{
    public function __invoke(
        Request $request,
        SchoolMonthlyReportService $reportService,
        SchoolMonthlyReportSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'school_level' => ['nullable', 'string', Rule::in(array_keys(SchoolReportLevel::options()))],
        ]);

        $schoolLevel = $validated['school_level'] ?? null;
        $report = $reportService->generate(
            (int) $validated['year'],
            (int) $validated['month'],
            $schoolLevel !== null ? SchoolReportLevel::fromValue($schoolLevel) : null
        );
        $path = $spreadsheet->create($report);
        $filename = 'laporan-bulanan-sekolah-'.$report['year'].'-'.str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT).'.xlsx';

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend();
    }
}

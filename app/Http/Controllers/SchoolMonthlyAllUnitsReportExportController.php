<?php

namespace App\Http\Controllers;

use App\Services\SchoolMonthlyAllUnitsReportService;
use App\Services\SchoolMonthlyAllUnitsReportSpreadsheet;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolMonthlyAllUnitsReportExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        SchoolMonthlyAllUnitsReportService $reportService,
        SchoolMonthlyAllUnitsReportSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $report = $reportService->generate((int) $validated['year'], (int) $validated['month']);
        $path = $spreadsheet->create($report);
        $filename = 'laporan-seluruh-unit-'.$report['year'].'-'.str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT).'.xlsx';

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend();
    }
}

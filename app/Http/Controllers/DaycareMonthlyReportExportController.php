<?php

namespace App\Http\Controllers;

use App\Services\DaycareMonthlyReportService;
use App\Services\DaycareMonthlyReportSpreadsheet;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DaycareMonthlyReportExportController extends Controller
{
    public function __invoke(
        Request $request,
        DaycareMonthlyReportService $reportService,
        DaycareMonthlyReportSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $report = $reportService->generate((int) $validated['year'], (int) $validated['month']);
        $path = $spreadsheet->create($report);
        $filename = 'laporan-bulanan-daycare-'
            .$report['year'].'-'
            .str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT).'.xlsx';

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend();
    }
}

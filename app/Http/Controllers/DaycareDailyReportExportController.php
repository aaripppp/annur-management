<?php

namespace App\Http\Controllers;

use App\Services\DaycareDailyReportService;
use App\Services\DaycareDailyReportSpreadsheet;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DaycareDailyReportExportController extends Controller
{
    public function __invoke(
        Request $request,
        DaycareDailyReportService $reportService,
        DaycareDailyReportSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ]);

        $report = $reportService->generate($validated['start_date'], $validated['end_date']);
        $path = $spreadsheet->create($report);
        $period = $report['is_single_day']
            ? $report['start_date']->format('Y-m-d')
            : $report['start_date']->format('Y-m-d').'-sampai-'.$report['end_date']->format('Y-m-d');
        $filename = 'laporan-harian-daycare-'.$period.'.xlsx';

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend();
    }
}

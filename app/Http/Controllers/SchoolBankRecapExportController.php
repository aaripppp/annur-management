<?php

namespace App\Http\Controllers;

use App\Services\SchoolBankRecapService;
use App\Services\SchoolBankRecapSpreadsheet;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolBankRecapExportController extends Controller
{
    public function __invoke(
        Request $request,
        SchoolBankRecapService $reportService,
        SchoolBankRecapSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'bank' => ['nullable', 'string', 'max:20'],
        ], [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ]);
        $report = $reportService->generate($validated['start_date'], $validated['end_date'], $validated['bank'] ?? 'all');
        $path = $spreadsheet->create($report);
        $period = $report['is_single_day']
            ? $report['start_date']->format('Y-m-d')
            : $report['start_date']->format('Y-m-d').'-sampai-'.$report['end_date']->format('Y-m-d');

        return response()->download(
            $path,
            'rekap-bank-sekolah-'.$period.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }
}

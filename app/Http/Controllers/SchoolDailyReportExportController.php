<?php

namespace App\Http\Controllers;

use App\Services\SchoolDailyReportService;
use App\Services\SchoolDailyReportSpreadsheet;
use App\Support\SchoolReportLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolDailyReportExportController extends Controller
{
    public function __invoke(
        Request $request,
        SchoolDailyReportService $reportService,
        SchoolDailyReportSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'school_level' => ['nullable', 'string', Rule::in(array_keys(SchoolReportLevel::options()))],
        ], [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ]);

        $schoolLevel = $validated['school_level'] ?? null;
        $report = $reportService->generate(
            $validated['start_date'],
            $validated['end_date'],
            $schoolLevel !== null ? SchoolReportLevel::fromValue($schoolLevel) : null
        );
        $path = $spreadsheet->create($report);
        $period = $report['is_single_day']
            ? $report['start_date']->format('Y-m-d')
            : $report['start_date']->format('Y-m-d').'-sampai-'.$report['end_date']->format('Y-m-d');
        $filename = 'laporan-harian-sekolah-'.$period.'.xlsx';

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SchoolDailyReportService;
use App\Support\SchoolReportLevel;
use App\Support\TransactionHistoryReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SchoolDailyReportHistoryPdfController extends Controller
{
    public function __invoke(Request $request, SchoolDailyReportService $reportService): Response
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'school_level' => ['nullable', 'string', Rule::in(array_keys(SchoolReportLevel::options()))],
        ], [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $schoolLevel = $validated['school_level'] ?? null;
        $report = $reportService->generate(
            $validated['start_date'],
            $validated['end_date'],
            $schoolLevel !== null ? SchoolReportLevel::fromValue($schoolLevel) : null,
        );
        $history = TransactionHistoryReport::groupDaily($report['detail_rows']);
        $printedAt = now()->settings(['locale' => 'id']);
        $document = [
            'unit' => $report['unit_name'],
            'jenjang' => $report['school_level'] !== null ? SchoolReportLevel::label($report['school_level']) : null,
            'period_title' => $report['period_title'],
            'period_label' => $report['period_label'],
            'printed_by' => $user->name,
            'printed_at' => $printedAt->translatedFormat('d F Y'),
        ];
        $period = $report['is_single_day']
            ? $report['start_date']->format('Y-m-d')
            : $report['start_date']->format('Y-m-d').'-sampai-'.$report['end_date']->format('Y-m-d');
        $filename = 'riwayat-transaksi-sekolah-'.$period.'.pdf';

        return Pdf::loadView('reports.school-daily-history-pdf', compact('report', 'document', 'history'))
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->setPaper([0, 0, 612.28, 935.43])
            ->stream($filename);
    }
}

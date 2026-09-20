<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DaycareDailyReportService;
use App\Support\DaycareReportDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DaycareDailyReportPdfController extends Controller
{
    private const REVIEWER_TITLE = 'Kepala Tata Usaha';

    private const REVIEWER_NAME = 'Windiarti, SE';

    public function __invoke(Request $request, DaycareDailyReportService $reportService): Response
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $report = $reportService->generate($validated['start_date'], $validated['end_date']);
        $localizedDate = $report['end_date']->settings(['locale' => 'id']);
        $document = [
            'unit' => DaycareReportDocument::UNIT_NAME,
            'period_title' => $report['period_title'],
            'period_label' => $report['period_label'],
            'approval' => [
                'admin_name' => $user->name,
                'reviewer_title' => self::REVIEWER_TITLE,
                'reviewer_name' => self::REVIEWER_NAME,
                'city_and_date' => DaycareReportDocument::CITY.', '.$localizedDate->translatedFormat('d F Y'),
                'report_creator_name' => $user->name,
            ],
        ];
        $period = $report['is_single_day']
            ? $report['start_date']->format('Y-m-d')
            : $report['start_date']->format('Y-m-d').'-sampai-'.$report['end_date']->format('Y-m-d');
        $filename = 'laporan-harian-daycare-'.$period.'.pdf';

        return Pdf::loadView('reports.daycare-daily-pdf', compact('report', 'document'))
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->setPaper([0, 0, 612.28, 935.43])
            ->stream($filename);
    }
}

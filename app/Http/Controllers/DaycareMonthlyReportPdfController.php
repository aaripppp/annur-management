<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DaycareMonthlyReportService;
use App\Support\DaycareReportDocument;
use App\Support\SchoolReportDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DaycareMonthlyReportPdfController extends Controller
{
    public function __invoke(Request $request, DaycareMonthlyReportService $reportService): Response
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $report = $reportService->generate((int) $validated['year'], (int) $validated['month']);
        $footerDate = $report['last_day']->locale('id')->translatedFormat('d F Y');
        $document = [
            'unit' => DaycareReportDocument::UNIT_NAME,
            'month_label' => $report['month_label_upper'],
            'approval' => SchoolReportDocument::approvalFor(
                $user,
                DaycareReportDocument::CITY.', '.$footerDate
            ),
        ];
        $filename = 'laporan-bulanan-daycare-'
            .$report['year'].'-'
            .str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT).'.pdf';

        return Pdf::loadView('reports.daycare-monthly-pdf', compact('report', 'document'))
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->setPaper([0, 0, 612.28, 935.43])
            ->stream($filename);
    }
}

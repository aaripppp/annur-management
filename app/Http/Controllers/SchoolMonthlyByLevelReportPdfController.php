<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SchoolMonthlyByLevelReportService;
use App\Support\SchoolReportDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SchoolMonthlyByLevelReportPdfController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, SchoolMonthlyByLevelReportService $reportService): Response
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $report = $reportService->generate((int) $validated['year'], (int) $validated['month']);
        $cityAndDate = SchoolReportDocument::CITY.', '.$report['last_day']
            ->settings(['locale' => 'id'])
            ->translatedFormat('d F Y');
        $document = [
            'unit' => $report['unit_name'],
            'month_label' => $report['month_label_upper'],
            'approval' => SchoolReportDocument::approvalFor($user, $cityAndDate),
        ];
        $filename = 'laporan-per-jenjang-'.$report['year'].'-'.str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT).'.pdf';

        return Pdf::loadView('reports.school-monthly-by-level-pdf', compact('report', 'document'))
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->setPaper([0, 0, 935.43, 612.28])
            ->stream($filename);
    }
}

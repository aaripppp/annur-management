<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SchoolMonthlyReportService;
use App\Services\StudentTargetArrearsReportService;
use App\Support\SchoolReportDocument;
use App\Support\SchoolReportLevel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SchoolMonthlyReportPdfController extends Controller
{
    private const APPROVER_TITLE = 'Direktur Keuangan';

    private const APPROVER_NAME = 'Nova Rabi\'ah Nurrohmah, SE';

    private const REVIEWER_TITLE = 'Kepala Tata Usaha';

    private const REVIEWER_NAME = 'Windiarti, SE';

    public function __invoke(
        Request $request,
        SchoolMonthlyReportService $reportService,
        StudentTargetArrearsReportService $targetArrearsReportService,
    ): Response {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'school_level' => ['nullable', 'string', Rule::in(array_keys(SchoolReportLevel::options()))],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $schoolLevel = isset($validated['school_level'])
            ? SchoolReportLevel::fromValue($validated['school_level'])
            : null;
        $report = $reportService->generate(
            (int) $validated['year'],
            (int) $validated['month'],
            $schoolLevel
        );
        $targetReport = $targetArrearsReportService->generateMonthlySummary(
            (int) $validated['month'],
            (int) $validated['year'],
            $schoolLevel,
        );
        $monthlyComparison = [
            'target_label' => 'TARGET BULAN '.$report['month_label_upper'],
            'target' => $targetReport['totals']['target'],
            'income' => $report['grand_total'],
            'outstanding' => (float) max($targetReport['totals']['target'] - $report['grand_total'], 0),
        ];
        $footerDate = $report['last_day']->settings(['locale' => 'id'])->translatedFormat('d F Y');
        $document = [
            'unit' => $report['unit_name'],
            'month_label' => $report['month_label_upper'],
            'approval' => [
                'approver_title' => self::APPROVER_TITLE,
                'approver_name' => self::APPROVER_NAME,
                'reviewer_title' => self::REVIEWER_TITLE,
                'reviewer_name' => self::REVIEWER_NAME,
                'city_and_date' => SchoolReportDocument::CITY.', '.$footerDate,
                'footer_unit' => 'TU '.$report['unit_name'],
                'report_creator_name' => $user->name,
            ],
        ];
        $filename = 'laporan-bulanan-sekolah-'
            .$report['year'].'-'
            .str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT).'.pdf';

        return Pdf::loadView('reports.school-monthly-pdf', compact('report', 'document', 'monthlyComparison'))
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->setPaper([0, 0, 935.43, 612.28])
            ->stream($filename);
    }
}

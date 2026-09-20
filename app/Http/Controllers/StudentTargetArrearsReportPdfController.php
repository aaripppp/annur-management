<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentTargetArrearsReportService;
use App\Support\SchoolReportDocument;
use App\Support\SchoolReportLevel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class StudentTargetArrearsReportPdfController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, StudentTargetArrearsReportService $reportService): Response
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in(StudentTargetArrearsReportService::modes())],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'academic_year' => ['nullable', 'string', 'max:9', 'required_unless:mode,monthly'],
            'school_level' => ['nullable', 'string', Rule::in(array_keys(SchoolReportLevel::options()))],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $report = $reportService->generate(
            $validated['mode'],
            (int) $validated['month'],
            (int) $validated['year'],
            $validated['academic_year'] ?? '',
            SchoolReportLevel::fromValue($validated['school_level'] ?? SchoolReportLevel::OPTION_ALL),
        );
        $document = [
            'unit' => $report['unit_name'],
            'approval' => [
                'approver_title' => 'Direktur Keuangan',
                'approver_name' => 'Nova Rabi\'ah Nurrohmah, SE',
                'reviewer_title' => 'Kepala Tata Usaha',
                'reviewer_name' => 'Windiarti, SE',
                'city_and_date' => SchoolReportDocument::CITY.', '.now()->settings(['locale' => 'id'])->translatedFormat('d F Y'),
                'footer_unit' => 'TU '.$report['unit_name'],
                'report_creator_name' => $user->name,
            ],
        ];
        $filename = 'target-tunggakan-'.$report['mode'].'-'.str_replace('/', '-', $report['period_label']).'.pdf';

        return Pdf::loadView('reports.student-target-arrears-pdf', compact('report', 'document'))
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->setPaper([0, 0, 612.28, 935.43])
            ->stream($filename);
    }
}

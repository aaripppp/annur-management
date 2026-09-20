<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SchoolBankRecapService;
use App\Support\SchoolReportDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SchoolBankRecapPdfController extends Controller
{
    public function __invoke(Request $request, SchoolBankRecapService $reportService): Response
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'bank' => ['nullable', 'string', 'max:20'],
        ], [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $report = $reportService->generate($validated['start_date'], $validated['end_date'], $validated['bank'] ?? 'all');
        $document = [
            'city_and_date' => SchoolReportDocument::CITY.', '.$report['end_date']->locale('id')->translatedFormat('d F Y'),
            'creator_name' => $user->name,
        ];
        $period = $report['is_single_day']
            ? $report['start_date']->format('Y-m-d')
            : $report['start_date']->format('Y-m-d').'-sampai-'.$report['end_date']->format('Y-m-d');

        return Pdf::loadView('reports.school-bank-recap-pdf', compact('report', 'document'))
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->setPaper([0, 0, 612.28, 935.43])
            ->stream('rekap-bank-sekolah-'.$period.'.pdf');
    }
}

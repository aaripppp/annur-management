<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentBillStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class StudentBillsPdfController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        Student $student,
        StudentBillStatementService $statementService,
    ): Response {
        $validated = $request->validate([
            'academic_year' => ['nullable', 'string', 'max:9'],
        ]);

        $academicYear = $validated['academic_year'] ?? '';

        $report = $statementService->generate($student, $academicYear);

        $filename = 'daftar-tagihan-'.Str::slug($student->nama_lengkap)
            .'-'.str_replace('/', '-', $academicYear === '' ? 'semua' : $academicYear)
            .'-'.now()->format('d-m-Y').'.pdf';

        return Pdf::loadView('reports.student-bills-pdf', ['report' => $report])
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->setPaper([0, 0, 612.28, 935.43])
            ->stream($filename);
    }
}

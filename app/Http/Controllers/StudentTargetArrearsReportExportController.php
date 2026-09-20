<?php

namespace App\Http\Controllers;

use App\Services\StudentTargetArrearsReportService;
use App\Services\StudentTargetArrearsReportSpreadsheet;
use App\Support\SchoolReportLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentTargetArrearsReportExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        StudentTargetArrearsReportService $reportService,
        StudentTargetArrearsReportSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        $validated = $request->validate($this->rules());
        $report = $reportService->generate(
            $validated['mode'],
            (int) $validated['month'],
            (int) $validated['year'],
            $validated['academic_year'] ?? '',
            SchoolReportLevel::fromValue($validated['school_level'] ?? SchoolReportLevel::OPTION_ALL),
        );
        $path = $spreadsheet->create($report);
        $filename = 'target-tunggakan-'.$report['mode'].'-'.str_replace('/', '-', $report['period_label']).'.xlsx';

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend();
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(StudentTargetArrearsReportService::modes())],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'academic_year' => ['nullable', 'string', 'max:9', 'required_unless:mode,monthly'],
            'school_level' => ['nullable', 'string', Rule::in(array_keys(SchoolReportLevel::options()))],
        ];
    }
}

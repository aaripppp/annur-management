<?php

namespace App\Console\Commands;

use App\Models\PpdbPublicSubmission;
use App\Models\ProspectiveStudent;
use App\Services\ProspectiveStudentBillGenerationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('ppdb:backfill-public-bills {--execute : Create missing applicable bills}')]
#[Description('Preview or create missing bills for completed public PPDB registrations')]
class BackfillPublicPpdbBills extends Command
{
    public function handle(ProspectiveStudentBillGenerationService $billGenerationService): int
    {
        $execute = (bool) $this->option('execute');
        $submissions = PpdbPublicSubmission::query()
            ->whereNotNull('completed_at')
            ->whereNotNull('prospective_student_id')
            ->with('prospectiveStudent')
            ->orderBy('id')
            ->get();

        $previewRows = $submissions
            ->map(function (PpdbPublicSubmission $submission) use ($billGenerationService): ?array {
                $prospectiveStudent = $submission->prospectiveStudent;

                if (! $prospectiveStudent) {
                    return null;
                }

                return $this->inspection($prospectiveStudent, $billGenerationService);
            })
            ->filter()
            ->values();

        $candidateIds = $previewRows
            ->where('missing', '>', 0)
            ->pluck('prospective_student_id')
            ->all();

        $this->line('Candidate prospect IDs: '.($candidateIds === [] ? '-' : implode(', ', $candidateIds)));

        if (! $execute) {
            $this->warn('DRY RUN: no bills were created. Use --execute to create missing applicable bills.');
            $this->renderRows($previewRows->map(fn (array $row): array => [
                ...$row,
                'created' => 0,
                'skipped' => $row['missing'],
            ])->all());

            return self::SUCCESS;
        }

        $resultRows = [];

        foreach ($previewRows as $previewRow) {
            $resultRows[] = DB::transaction(function () use ($previewRow, $billGenerationService): array {
                $prospectiveStudent = ProspectiveStudent::query()
                    ->whereKey($previewRow['prospective_student_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $prospectiveStudent) {
                    return [
                        ...$previewRow,
                        'created' => 0,
                        'skipped' => $previewRow['missing'],
                    ];
                }

                $before = $this->inspection($prospectiveStudent, $billGenerationService);
                $created = $billGenerationService->generateFor($prospectiveStudent)->count();

                return [
                    ...$before,
                    'created' => $created,
                    'skipped' => max(0, $before['missing'] - $created),
                ];
            });
        }

        $this->renderRows($resultRows);

        return self::SUCCESS;
    }

    /**
     * @return array{prospective_student_id: int, applicable: int, existing: int, missing: int}
     */
    private function inspection(
        ProspectiveStudent $prospectiveStudent,
        ProspectiveStudentBillGenerationService $billGenerationService,
    ): array {
        $applicableBills = $billGenerationService->applicableBillsFor($prospectiveStudent);
        $existingKeys = $prospectiveStudent->bills()
            ->get(['payment_type_id', 'academic_year'])
            ->mapWithKeys(fn ($bill): array => [
                $bill->payment_type_id.'|'.$bill->academic_year => true,
            ]);
        $existing = $applicableBills->filter(fn (array $bill): bool => $existingKeys->has(
            $bill['payment_type']->id.'|'.$bill['academic_year']
        ))->count();

        return [
            'prospective_student_id' => $prospectiveStudent->id,
            'applicable' => $applicableBills->count(),
            'existing' => $existing,
            'missing' => $applicableBills->count() - $existing,
        ];
    }

    /** @param array<int, array<string, int>> $rows */
    private function renderRows(array $rows): void
    {
        $this->table(
            ['Prospect ID', 'Applicable', 'Existing', 'Missing', 'Created', 'Skipped'],
            collect($rows)->map(fn (array $row): array => [
                $row['prospective_student_id'],
                $row['applicable'],
                $row['existing'],
                $row['missing'],
                $row['created'],
                $row['skipped'],
            ])->all(),
        );

        $this->info(sprintf(
            'Created: %d; Existing: %d; Skipped: %d.',
            collect($rows)->sum('created'),
            collect($rows)->sum('existing'),
            collect($rows)->sum('skipped'),
        ));
    }
}

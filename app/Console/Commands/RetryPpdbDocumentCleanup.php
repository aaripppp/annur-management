<?php

namespace App\Console\Commands;

use App\Services\PpdbDocumentCleanupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ppdb:retry-document-cleanup {--limit=100 : Maximum pending cleanup records to retry}')]
#[Description('Retry safely recorded PPDB document cleanup failures')]
class RetryPpdbDocumentCleanup extends Command
{
    public function handle(PpdbDocumentCleanupService $cleanupService): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 500) {
            $this->components->error('The limit must be an integer between 1 and 500.');

            return self::FAILURE;
        }

        $result = $cleanupService->retryPending($limit);

        $this->components->info(sprintf(
            'Attempted: %d. Resolved: %d. Pending: %d.',
            $result['attempted'],
            $result['resolved'],
            $result['pending'],
        ));

        return $result['pending'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}

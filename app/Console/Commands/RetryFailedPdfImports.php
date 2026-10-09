<?php

namespace App\Console\Commands;

use App\Jobs\ProcessComplaintPdfImport;
use App\Models\ComplaintPdfImport;
use Illuminate\Console\Command;

class RetryFailedPdfImports extends Command
{
    protected $signature = 'complaints:retry-pdf-imports
                            {--limit=200 : Max failed rows to re-queue}
                            {--status=failed : Status to retry (failed|extracted)}
                            {--sync : Process inline}';

    protected $description = 'Re-queue failed (or extracted-but-not-applied) PDF imports';

    public function handle(): int
    {
        $status = (string) $this->option('status');
        $limit = max(1, (int) $this->option('limit'));

        $rows = ComplaintPdfImport::query()
            ->where('status', $status)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No rows with status={$status}.");

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($rows as $import) {
            $import->update([
                'status'        => ComplaintPdfImport::STATUS_PENDING,
                'error_message' => null,
            ]);

            if ($this->option('sync')) {
                $job = new ProcessComplaintPdfImport($import->id, $import->user_id, true, true);
                $job->handle(app(\App\Services\ComplaintPdfImportService::class));
                $import = $import->fresh();
                if ($import->status === ComplaintPdfImport::STATUS_FAILED) {
                    $this->error("FAIL {$import->original_filename}: {$import->error_message}");
                } else {
                    $this->info("OK {$import->original_filename} → {$import->status}");
                }
            } else {
                ProcessComplaintPdfImport::dispatch($import->id, $import->user_id, true, true);
            }
            $n++;
        }

        $this->info("Re-queued {$n} import(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\FinalizeOcrImport;
use App\Jobs\ProcessOcrChunk;
use App\Models\ComplaintPdfImport;
use Illuminate\Console\Command;

/**
 * Re-queues imports whose worker died (server restart, killed process). Safe to run
 * repeatedly: processing resumes from the page checkpoint and record creation is idempotent.
 */
class OcrRecover extends Command
{
    protected $signature = 'ocr:recover {--minutes=30 : Consider an import stalled after this many idle minutes} {--dry-run}';

    protected $description = 'Re-queue OCR imports stalled in queued/processing state';

    public function handle(): int
    {
        $stalled = ComplaintPdfImport::whereNotNull('circle_id')
            ->whereIn('status', [ComplaintPdfImport::STATUS_QUEUED, ComplaintPdfImport::STATUS_PROCESSING])
            ->where('updated_at', '<', now()->subMinutes((int) $this->option('minutes')))
            ->get(['id', 'status', 'page_count', 'pages_done']);

        foreach ($stalled as $import) {
            $finished = $import->page_count && (int) $import->pages_done >= (int) $import->page_count;
            $this->line(sprintf('#%d %s %d/%s -> %s', $import->id, $import->status, $import->pages_done, $import->page_count ?? '?', $finished ? 'finalize' : 'process'));
            if (!$this->option('dry-run')) {
                $import->touch();
                $finished ? FinalizeOcrImport::dispatch($import->id) : ProcessOcrChunk::dispatch($import->id);
            }
        }
        $this->info($stalled->count() . ' stalled import(s)' . ($this->option('dry-run') ? ' (dry run)' : ' re-queued'));

        return self::SUCCESS;
    }
}

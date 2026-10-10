<?php

namespace App\Jobs;

use App\Models\ComplaintPdfImport;
use App\Services\Ocr\OcrPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Extracts fields from all stored pages and either imports (rules satisfied) or queues for review. */
class FinalizeOcrImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $importId)
    {
        $this->onQueue(config('ocr.queue'));
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(OcrPipeline $pipeline): void
    {
        $lock = Cache::lock(ProcessOcrChunk::lockKey($this->importId), 900);
        if (!$lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $import = ComplaintPdfImport::find($this->importId);
            if (!$import || !$import->circle_id || $import->status !== ComplaintPdfImport::STATUS_PROCESSING
                || (int) $import->pages_done < (int) $import->page_count) {
                return;
            }
            $pipeline->finalize($import);
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        if ($import = ComplaintPdfImport::find($this->importId)) {
            app(OcrPipeline::class)->markFailed($import, 'Field extraction failed: ' . $e->getMessage());
        }
    }
}

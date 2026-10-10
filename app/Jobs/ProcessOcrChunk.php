<?php

namespace App\Jobs;

use App\Models\ComplaintPdfImport;
use App\Services\Ocr\OcrPipeline;
use App\Services\Ocr\OcrRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Processes the next unfinished page range of one import, then queues itself again
 * (or the finalize step). Progress lives in complaint_pdf_import_pages, so a crash or
 * retry resumes where it stopped. Only the import id travels through the queue; the
 * circle is read from the import row, never from job input.
 */
class ProcessOcrChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $importId)
    {
        $this->onQueue(config('ocr.queue'));
    }

    public function timeout(): int
    {
        return (int) config('ocr.chunk_timeout') + 60;
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public static function lockKey(int $importId): string
    {
        return 'ocr-import-lock-' . $importId;
    }

    public function handle(OcrPipeline $pipeline): void
    {
        // One worker per import at a time, whatever the worker concurrency. The lock is
        // released before the follow-up job is queued so the next chunk can start at once.
        $lock = Cache::lock(self::lockKey($this->importId), (int) config('ocr.chunk_timeout') + 120);
        if (!$lock->get()) {
            $this->release(30);

            return;
        }

        $next = null;
        try {
            $import = ComplaintPdfImport::find($this->importId);
            if (!$import || !$import->circle_id || in_array($import->status, [
                ComplaintPdfImport::STATUS_IMPORTED, ComplaintPdfImport::STATUS_NEEDS_REVIEW, ComplaintPdfImport::STATUS_FAILED,
            ], true)) {
                return;
            }

            $import->increment('attempts');
            try {
                $next = $pipeline->processNextChunk($import) ? self::class : FinalizeOcrImport::class;
            } catch (OcrRejected $e) {
                $pipeline->markFailed($import, 'Rejected: ' . $e->getMessage());
            }
        } finally {
            $lock->release();
        }

        if ($next) {
            $next::dispatch($this->importId);
        }
    }

    public function failed(\Throwable $e): void
    {
        if ($import = ComplaintPdfImport::find($this->importId)) {
            app(OcrPipeline::class)->markFailed($import, 'Processing failed after retries: ' . $e->getMessage());
        }
    }
}

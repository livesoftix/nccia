<?php

namespace App\Jobs;

use App\Models\ComplaintPdfImport;
use App\Models\User;
use App\Services\ComplaintPdfImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessComplaintPdfImport implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public int $importId,
        public ?int $userId = null,
        public bool $autoApply = true,
        public bool $copyAttachment = true,
    ) {
        $this->onQueue('pdf-imports');
    }

    public function handle(ComplaintPdfImportService $service): void
    {
        $import = ComplaintPdfImport::find($this->importId);
        if (!$import) {
            return;
        }

        try {
            $import = $service->process($import);

            if ($this->autoApply && $import->status === ComplaintPdfImport::STATUS_EXTRACTED) {
                $user = $this->userId
                    ? User::with('roles')->find($this->userId)
                    : $import->user?->load('roles');

                if ($user) {
                    $service->applyToSystem($import, $user, true, $this->copyAttachment);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Complaint PDF import job failed', [
                'import_id' => $this->importId,
                'error'     => $e->getMessage(),
            ]);

            $import?->update([
                'status'        => ComplaintPdfImport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'processed_at'  => now(),
            ]);
        }
    }
}

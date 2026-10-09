<?php

namespace App\Console\Commands;

use App\Services\AuditLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyAuditIntegrity extends Command
{
    protected $signature = 'security:audit-verify';
    protected $description = 'Verify the complete audit chain and emit a redacted operational alert on failure';

    public function handle(): int
    {
        try {
            $result = AuditLedgerService::verify();
            if ($result['ok']) {
                $this->info('Audit integrity verified; entries checked: '.$result['checked']);
                return self::SUCCESS;
            }
            Log::critical('Audit integrity check failed', ['checked' => $result['checked'], 'broken_at' => $result['broken_at']]);
        } catch (\Throwable $e) {
            Log::critical('Audit integrity check unavailable', ['exception_type' => get_class($e)]);
        }
        $this->error('Audit integrity could not be verified. Investigate the protected audit store.');
        return self::FAILURE;
    }
}

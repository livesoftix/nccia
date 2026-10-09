<?php

namespace App\Console\Commands;

use App\Models\AuditLedger;
use App\Services\AuditLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptAuditDetails extends Command
{
    protected $signature = 'security:audit-encrypt {--apply : Encrypt legacy details after backup and key verification}';
    protected $description = 'Count or encrypt legacy audit details without changing their integrity hashes';

    public function handle(): int
    {
        if (!AuditLedgerService::verify()['ok']) {
            $this->error('Audit chain verification failed; investigate before migration.');
            return self::FAILURE;
        }
        $query = AuditLedger::query()->whereNull('properties_ciphertext')->whereNotNull('properties');
        $this->info('Legacy detail records: ' . $query->count());
        if (!$this->option('apply')) {
            $this->info('Preview only. Use --apply after verifying a recoverable backup and encryption key.');
            return self::SUCCESS;
        }
        $converted = 0;
        $query->chunkById(100, function ($entries) use (&$converted) {
            DB::transaction(function () use ($entries, &$converted) {
                DB::table('audit_ledger_head')->where('id', 1)->lockForUpdate()->first();
                foreach ($entries as $entry) {
                    $properties = $entry->properties;
                    if ($properties === null) {
                        continue;
                    }
                    $ciphertext = Crypt::encryptString(json_encode($properties, JSON_THROW_ON_ERROR));
                    $converted += DB::table('audit_ledger')->where('id', $entry->id)
                        ->whereNull('properties_ciphertext')->update(['properties_ciphertext' => $ciphertext, 'properties' => null]);
                }
            }, 3);
        });
        if (!AuditLedgerService::verify()['ok']) {
            $this->error('Post-migration integrity verification failed.');
            return self::FAILURE;
        }
        $this->info('Encrypted detail records: ' . $converted . '; audit integrity verified.');
        return self::SUCCESS;
    }
}

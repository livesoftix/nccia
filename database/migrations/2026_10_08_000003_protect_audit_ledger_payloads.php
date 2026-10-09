<?php

use App\Services\AuditLedgerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_ledger', function (Blueprint $table) {
            $table->text('properties_ciphertext')->nullable();
            $table->unsignedSmallInteger('hash_version')->default(1);
        });
        Schema::create('audit_ledger_head', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('last_id')->default(0);
            $table->unsignedBigInteger('entries')->default(0);
            $table->char('hash', 64);
            $table->char('mac', 64);
        });
        $last = DB::table('audit_ledger')->orderByDesc('id')->first();
        $head = ['last_id' => (int) ($last->id ?? 0), 'entries' => DB::table('audit_ledger')->count(),
            'hash' => $last->hash ?? str_repeat('0', 64)];
        DB::table('audit_ledger_head')->insert(['id' => 1] + $head + ['mac' => AuditLedgerService::headMac($head)]);
    }

    public function down(): void
    {
        if (DB::table('audit_ledger')->where('hash_version', 2)->exists()) {
            throw new \RuntimeException('Restore a paired application/database backup to roll back v2 audit metadata.');
        }
        // Rollback cannot discard encrypted audit evidence.
        DB::transaction(function () {
            foreach (\App\Models\AuditLedger::query()->whereNotNull('properties_ciphertext')->cursor() as $entry) {
                DB::table('audit_ledger')->where('id', $entry->id)->update([
                    'properties' => json_encode($entry->properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
            }
        });
        Schema::dropIfExists('audit_ledger_head');
        Schema::table('audit_ledger', fn (Blueprint $table) => $table->dropColumn(['properties_ciphertext', 'hash_version']));
    }
};

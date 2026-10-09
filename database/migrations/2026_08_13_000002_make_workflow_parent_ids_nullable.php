<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Direct VIP / departmental cases may skip the prior stage:
     * Verification/Enquiry without complaint, Case/FIR without enquiry.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        $this->nullableForeignId('verifications', 'complaint_id', 'complaints', $driver);
        $this->nullableForeignId('enquiries', 'complaint_id', 'complaints', $driver);
        $this->nullableForeignId('cases', 'enquiry_id', 'enquiries', $driver);
    }

    public function down(): void
    {
        // Non-reversible safely when null rows exist.
    }

    private function nullableForeignId(string $table, string $column, string $parent, string $driver): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            try {
                $blueprint->dropForeign([$column]);
            } catch (\Throwable $e) {
                // FK name may differ across environments.
            }
        });

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED NULL");
        } else {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->unsignedBigInteger($column)->nullable()->change();
            });
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $parent) {
            $blueprint->foreign($column)->references('id')->on($parent)->nullOnDelete();
        });
    }
};

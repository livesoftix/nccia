<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requirement #5 — scale readiness (application half).
 *
 * The hot query paths filter heavily on `status` and on (owning officer +
 * status) — dashboards, sidebar counts, work queues and the visibleTo scopes.
 * Foreign-key columns (circle_id, complaint_id, officer ids, enquiry_id) are
 * already indexed by the constrained() foreign keys; `status` and the officer+
 * status composites are not. These indexes turn repeated full-table scans into
 * index range scans, which is what keeps list and dashboard queries fast as the
 * tables grow.
 *
 * Each add is guarded so the migration is safe to run on a populated production
 * database and idempotent if an index already exists.
 */
return new class extends Migration
{
    /** @var array<string, array<int, array{cols: array<int,string>, name: string}>> */
    private array $plan = [
        'complaints' => [
            ['cols' => ['status'], 'name' => 'idx_perf_complaints_status'],
            ['cols' => ['circle_id', 'status'], 'name' => 'idx_perf_complaints_circle_status'],
            ['cols' => ['created_at'], 'name' => 'idx_perf_complaints_created'],
        ],
        'verifications' => [
            ['cols' => ['status'], 'name' => 'idx_perf_verifications_status'],
            ['cols' => ['verification_officer_id', 'status'], 'name' => 'idx_perf_verifications_officer_status'],
        ],
        'enquiries' => [
            ['cols' => ['status'], 'name' => 'idx_perf_enquiries_status'],
            ['cols' => ['enquiry_officer_id', 'status'], 'name' => 'idx_perf_enquiries_officer_status'],
        ],
        'cases' => [
            ['cols' => ['status'], 'name' => 'idx_perf_cases_status'],
            ['cols' => ['investigation_officer_id', 'status'], 'name' => 'idx_perf_cases_officer_status'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->plan as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $index) {
                // Skip if any column is missing, or the index already exists.
                foreach ($index['cols'] as $col) {
                    if (! Schema::hasColumn($table, $col)) {
                        continue 2;
                    }
                }
                try {
                    Schema::table($table, function (Blueprint $t) use ($index) {
                        $t->index($index['cols'], $index['name']);
                    });
                } catch (\Throwable $e) {
                    // Index already present (or DB rejected a duplicate) — safe to ignore.
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->plan as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $index) {
                try {
                    Schema::table($table, function (Blueprint $t) use ($index) {
                        $t->dropIndex($index['name']);
                    });
                } catch (\Throwable $e) {
                    // Not present — nothing to drop.
                }
            }
        }
    }
};

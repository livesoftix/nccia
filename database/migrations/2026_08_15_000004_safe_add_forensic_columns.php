<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safe: Add forensic_requests columns only if they don't exist
        Schema::table('forensic_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('forensic_requests', 'priority')) {
                $table->string('priority', 20)->default('normal')->after('status');
            }
            if (!Schema::hasColumn('forensic_requests', 'findings')) {
                $table->text('findings')->nullable()->after('note');
            }
            if (!Schema::hasColumn('forensic_requests', 'lab_notes')) {
                $table->text('lab_notes')->nullable()->after('findings');
            }
            if (!Schema::hasColumn('forensic_requests', 'report_attachment_path')) {
                $table->string('report_attachment_path')->nullable()->after('attachment_path');
            }
            if (!Schema::hasColumn('forensic_requests', 'submitted_to_ad_at')) {
                $table->timestamp('submitted_to_ad_at')->nullable()->after('report_ready_at');
            }
        });

        // Safe: Add forensic_request_items columns only if they don't exist
        if (Schema::hasTable('forensic_request_items')) {
            Schema::table('forensic_request_items', function (Blueprint $table) {
                if (!Schema::hasColumn('forensic_request_items', 'imei2')) {
                    $table->string('imei2', 64)->nullable()->after('imei');
                }
                if (!Schema::hasColumn('forensic_request_items', 'storage_capacity')) {
                    $table->string('storage_capacity', 64)->nullable()->after('serial_no');
                }
                if (!Schema::hasColumn('forensic_request_items', 'condition')) {
                    $table->string('condition', 100)->nullable()->after('storage_capacity');
                }
                if (!Schema::hasColumn('forensic_request_items', 'seized_from')) {
                    $table->string('seized_from', 255)->nullable()->after('condition');
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: no rollback
    }
};

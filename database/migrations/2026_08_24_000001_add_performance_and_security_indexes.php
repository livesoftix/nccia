<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('enquiries', 'circle_id')) {
            $this->addIndexIfMissing('enquiries', ['circle_id', 'status'], 'idx_enquiries_circle_status');
        }
        $this->addIndexIfMissing('enquiries', ['enquiry_officer_id', 'status'], 'idx_enquiries_officer_status');
        $this->addIndexIfMissing('enquiries', ['created_at'], 'idx_enquiries_created_at');

        $this->addIndexIfMissing('complaints', ['circle_id', 'status'], 'idx_complaints_circle_status');
        $this->addIndexIfMissing('complaints', ['created_at'], 'idx_complaints_created_at');

        $this->addIndexIfMissing('forensic_requests', ['status', 'destination'], 'idx_forensic_status_dest');
        $this->addIndexIfMissing('forensic_requests', ['assigned_to', 'status'], 'idx_forensic_assigned_status');
        $this->addIndexIfMissing('forensic_requests', ['created_at'], 'idx_forensic_created_at');

        $this->addIndexIfMissing('forensic_request_items', ['forensic_request_id'], 'idx_items_req_id');

        $this->addIndexIfMissing('enquiry_notices', ['enquiry_id', 'notice_number'], 'idx_notices_enq_num');

        $this->addIndexIfMissing('users', ['role', 'circle_id'], 'idx_users_role_circle');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('enquiries', 'idx_enquiries_circle_status');
        $this->dropIndexIfExists('enquiries', 'idx_enquiries_officer_status');
        $this->dropIndexIfExists('enquiries', 'idx_enquiries_created_at');
        $this->dropIndexIfExists('complaints', 'idx_complaints_circle_status');
        $this->dropIndexIfExists('complaints', 'idx_complaints_created_at');
        $this->dropIndexIfExists('forensic_requests', 'idx_forensic_status_dest');
        $this->dropIndexIfExists('forensic_requests', 'idx_forensic_assigned_status');
        $this->dropIndexIfExists('forensic_requests', 'idx_forensic_created_at');
        $this->dropIndexIfExists('forensic_request_items', 'idx_items_req_id');
        $this->dropIndexIfExists('enquiry_notices', 'idx_notices_enq_num');
        $this->dropIndexIfExists('users', 'idx_users_role_circle');
    }

    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($tableName, $column)) {
                return;
            }
        }

        Schema::table($tableName, function (Blueprint $blueprint) use ($columns, $indexName) {
            try {
                $blueprint->index($columns, $indexName);
            } catch (\Throwable $e) {
                // Index already exists or engine rejected a duplicate.
            }
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $blueprint) use ($indexName) {
            try {
                $blueprint->dropIndex($indexName);
            } catch (\Throwable $e) {
            }
        });
    }
};

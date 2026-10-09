<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (!$this->hasIndex('cases', 'cases_status_updated_at_index')) {
                $table->index(['status', 'updated_at'], 'cases_status_updated_at_index');
            }
        });

        Schema::table('enquiries', function (Blueprint $table) {
            if (!$this->hasIndex('enquiries', 'enquiries_status_closure_reason_index')) {
                $table->index(['status', 'closure_reason'], 'enquiries_status_closure_reason_index');
            }
        });

        Schema::table('activity_log', function (Blueprint $table) {
            if (!$this->hasIndex('activity_log', 'activity_log_created_at_index')) {
                $table->index('created_at', 'activity_log_created_at_index');
            }
        });

        Schema::table('case_activities', function (Blueprint $table) {
            if (!$this->hasIndex('case_activities', 'case_activities_case_id_id_index')) {
                $table->index(['case_id', 'id'], 'case_activities_case_id_id_index');
            }
        });

        Schema::table('enquiry_activities', function (Blueprint $table) {
            if (!$this->hasIndex('enquiry_activities', 'enquiry_activities_enquiry_id_id_index')) {
                $table->index(['enquiry_id', 'id'], 'enquiry_activities_enquiry_id_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex('cases_status_updated_at_index');
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex('enquiries_status_closure_reason_index');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_created_at_index');
        });

        Schema::table('case_activities', function (Blueprint $table) {
            $table->dropIndex('case_activities_case_id_id_index');
        });

        Schema::table('enquiry_activities', function (Blueprint $table) {
            $table->dropIndex('enquiry_activities_enquiry_id_id_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($idx) => ($idx->name ?? '') === $indexName);
        }

        $database = $connection->getDatabaseName();
        $rows = $connection->select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return count($rows) > 0;
    }
};

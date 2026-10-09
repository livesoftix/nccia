<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addPriorityColumn('enquiries');
        $this->addPriorityColumn('cases');
    }

    public function down(): void
    {
        $this->dropPriorityColumn('enquiries');
        $this->dropPriorityColumn('cases');
    }

    private function addPriorityColumn(string $tableName): void
    {
        if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'priority')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $blueprint) {
            try {
                $blueprint->string('priority', 20)->nullable()->after('status');
            } catch (\Throwable $error) {
                // Column already exists.
            }
        });
    }

    private function dropPriorityColumn(string $tableName): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'priority')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $blueprint) {
            try {
                $blueprint->dropColumn('priority');
            } catch (\Throwable $error) {
            }
        });
    }
};

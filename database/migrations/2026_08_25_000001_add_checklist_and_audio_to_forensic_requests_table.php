<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('forensic_requests')) {
            return;
        }

        $this->addBooleanIfMissing('forensic_requests', 'checklist_tech_report', 'note');
        $this->addBooleanIfMissing('forensic_requests', 'checklist_seizure_memo', 'checklist_tech_report');
        $this->addBooleanIfMissing('forensic_requests', 'checklist_fir_copy', 'checklist_seizure_memo');
        $this->addBooleanIfMissing('forensic_requests', 'checklist_scope_letter', 'checklist_fir_copy');
        $this->addTextIfMissing('forensic_requests', 'audio_script', 'checklist_scope_letter');
        $this->addStringIfMissing('forensic_requests', 'audio_source_path', 'audio_script', 500);
        $this->addStringIfMissing('forensic_requests', 'audio_sample_path', 'audio_source_path', 500);
        $this->addStringIfMissing('forensic_requests', 'routed_to', 'audio_sample_path', 255);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('forensic_requests', 'routed_to');
        $this->dropColumnIfExists('forensic_requests', 'audio_sample_path');
        $this->dropColumnIfExists('forensic_requests', 'audio_source_path');
        $this->dropColumnIfExists('forensic_requests', 'audio_script');
        $this->dropColumnIfExists('forensic_requests', 'checklist_scope_letter');
        $this->dropColumnIfExists('forensic_requests', 'checklist_fir_copy');
        $this->dropColumnIfExists('forensic_requests', 'checklist_seizure_memo');
        $this->dropColumnIfExists('forensic_requests', 'checklist_tech_report');
    }

    private function addBooleanIfMissing(string $tableName, string $column, string $after): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $blueprint) use ($column, $after) {
            try {
                $blueprint->boolean($column)->default(false)->after($after);
            } catch (\Throwable $error) {
                // Column already exists.
            }
        });
    }

    private function addTextIfMissing(string $tableName, string $column, string $after): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $blueprint) use ($column, $after) {
            try {
                $blueprint->text($column)->nullable()->after($after);
            } catch (\Throwable $error) {
                // Column already exists.
            }
        });
    }

    private function addStringIfMissing(string $tableName, string $column, string $after, int $length): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $blueprint) use ($column, $after, $length) {
            try {
                $blueprint->string($column, $length)->nullable()->after($after);
            } catch (\Throwable $error) {
                // Column already exists.
            }
        });
    }

    private function dropColumnIfExists(string $tableName, string $column): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $blueprint) use ($column) {
            try {
                $blueprint->dropColumn($column);
            } catch (\Throwable $error) {
            }
        });
    }
};

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

        $this->addStringIfMissing('forensic_requests', 'audio_sample_2_path', 'audio_sample_path', 500);
        $this->addBooleanIfMissing('forensic_requests', 'checklist_audio_samples', 'checklist_scope_letter');
        $this->addBooleanIfMissing('forensic_requests', 'checklist_summons', 'checklist_audio_samples');
        $this->addBooleanIfMissing('forensic_requests', 'checklist_usb', 'checklist_summons');
        $this->addBooleanIfMissing('forensic_requests', 'checklist_transcript', 'checklist_usb');
    }

    public function down(): void
    {
        $this->dropColumnIfExists('forensic_requests', 'checklist_transcript');
        $this->dropColumnIfExists('forensic_requests', 'checklist_usb');
        $this->dropColumnIfExists('forensic_requests', 'checklist_summons');
        $this->dropColumnIfExists('forensic_requests', 'checklist_audio_samples');
        $this->dropColumnIfExists('forensic_requests', 'audio_sample_2_path');
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

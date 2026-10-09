<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forensic_requests', function (Blueprint $table) {
            $table->text('brief_contents')->nullable()->after('destination');
            $table->text('analysis_scope')->nullable()->after('note');
        });
        
        if (!Schema::hasColumn('enquiries', 'brief_allegation')) {
            Schema::table('enquiries', function (Blueprint $table) {
                $table->text('brief_allegation')->nullable()->after('cfr_summary');
            });
        }
    }

    public function down(): void
    {
        Schema::table('forensic_requests', function (Blueprint $table) {
            $table->dropColumn(['brief_contents', 'analysis_scope']);
        });
        Schema::table('enquiries', function (Blueprint $table) {
            if (Schema::hasColumn('enquiries', 'brief_allegation')) {
                $table->dropColumn('brief_allegation');
            }
        });
    }
};

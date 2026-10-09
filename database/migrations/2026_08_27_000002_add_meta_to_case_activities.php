<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('case_activities') || Schema::hasColumn('case_activities', 'meta')) {
            return;
        }

        Schema::table('case_activities', function (Blueprint $blueprint) {
            try {
                $blueprint->json('meta')->nullable()->after('description');
            } catch (\Throwable $error) {
                // Column already exists.
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('case_activities') || !Schema::hasColumn('case_activities', 'meta')) {
            return;
        }

        Schema::table('case_activities', function (Blueprint $blueprint) {
            try {
                $blueprint->dropColumn('meta');
            } catch (\Throwable $error) {
            }
        });
    }
};

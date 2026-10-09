<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('verifications') && !Schema::hasColumn('verifications', 'accused')) {
            Schema::table('verifications', function (Blueprint $table) {
                $table->json('accused')->nullable()->after('direct_info');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('verifications') && Schema::hasColumn('verifications', 'accused')) {
            Schema::table('verifications', function (Blueprint $table) {
                $table->dropColumn('accused');
            });
        }
    }
};

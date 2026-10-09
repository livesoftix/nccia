<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('verification_reports', 'victim_email')) {
                $table->string('victim_email')->nullable()->after('victim_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('verification_reports', function (Blueprint $table) {
            if (Schema::hasColumn('verification_reports', 'victim_email')) {
                $table->dropColumn('victim_email');
            }
        });
    }
};

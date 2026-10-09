<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_reports', function (Blueprint $table) {
            $table->json('transactions')->nullable()->after('evidence');
        });
    }

    public function down(): void
    {
        Schema::table('verification_reports', function (Blueprint $table) {
            $table->dropColumn('transactions');
        });
    }
};

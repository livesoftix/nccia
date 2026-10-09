<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_reports', function (Blueprint $table) {
            $table->string('recommendation', 50)->nullable()->after('recommendation_full');
            $table->string('closure_reason', 50)->nullable()->after('recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('verification_reports', function (Blueprint $table) {
            $table->dropColumn(['recommendation', 'closure_reason']);
        });
    }
};

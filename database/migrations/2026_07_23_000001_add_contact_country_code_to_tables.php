<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('contact_country_code', 8)->nullable()->default('+92')->after('contact_no');
        });

        Schema::table('investigation_officers', function (Blueprint $table) {
            $table->string('contact_country_code', 8)->nullable()->default('+92')->after('contact_no');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('contact_country_code');
        });

        Schema::table('investigation_officers', function (Blueprint $table) {
            $table->dropColumn('contact_country_code');
        });
    }
};

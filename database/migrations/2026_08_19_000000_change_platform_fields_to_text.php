<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('complaints', function (Blueprint $table) {
            $table->text('platform_profile_page')->nullable()->change();
            $table->text('platform_username')->nullable()->change();
        });
    }
    public function down(): void {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('platform_profile_page', 255)->nullable()->change();
            $table->string('platform_username', 255)->nullable()->change();
        });
    }
};


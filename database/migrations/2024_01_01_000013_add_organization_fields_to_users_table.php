<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('email');
            $table->string('designation')->nullable()->after('role');
            $table->foreignId('circle_id')->nullable()->constrained()->nullOnDelete()->after('designation');
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete()->after('circle_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
            $table->dropConstrainedForeignId('circle_id');
            $table->dropColumn(['role', 'designation']);
        });
    }
};

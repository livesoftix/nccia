<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('source')->nullable()->after('id');
            $table->string('status', 20)->default('incomplete')->after('operator_remarks');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->foreignId('circle_id')->nullable()->constrained()->nullOnDelete()->after('operator_id');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('circle_id');
            $table->dropConstrainedForeignId('operator_id');
            $table->dropColumn(['source', 'status']);
        });
    }
};

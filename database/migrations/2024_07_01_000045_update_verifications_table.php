<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete()->after('verification_officer_id');
            $table->foreignId('transfer_circle_id')->nullable()->constrained('circles')->nullOnDelete()->after('transfer_department');
            $table->dateTime('submitted_at')->nullable()->after('assigned_at');
            $table->dateTime('approved_at')->nullable()->after('submitted_at');

            // Drop old string columns replaced by FKs
            $table->dropColumn('transfer_circle');
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropForeign(['assigned_by']);
            $table->dropColumn('assigned_by');
            $table->dropForeign(['transfer_circle_id']);
            $table->dropColumn('transfer_circle_id');
            $table->dropColumn('submitted_at');
            $table->dropColumn('approved_at');
            $table->string('transfer_circle')->nullable();
        });
    }
};

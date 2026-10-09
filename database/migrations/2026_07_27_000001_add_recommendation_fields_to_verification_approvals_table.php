<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_approvals', function (Blueprint $table) {
            $table->string('recommendation', 30)->nullable()->after('decision');
            $table->string('closure_reason', 30)->nullable()->after('recommendation');
            $table->foreignId('merge_complaint_id')->nullable()->constrained('complaints')->nullOnDelete()->after('closure_reason');
            $table->string('transfer_department')->nullable()->after('merge_complaint_id');
            $table->foreignId('transfer_circle_id')->nullable()->constrained('circles')->nullOnDelete()->after('transfer_department');
        });
    }

    public function down(): void
    {
        Schema::table('verification_approvals', function (Blueprint $table) {
            $table->dropForeign(['merge_complaint_id']);
            $table->dropForeign(['transfer_circle_id']);
            $table->dropColumn(['recommendation', 'closure_reason', 'merge_complaint_id', 'transfer_department', 'transfer_circle_id']);
        });
    }
};

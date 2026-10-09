<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->text('cfr_summary')->nullable()->after('recommendation');
            $table->date('reg_date')->nullable()->after('enquiry_number');
            $table->date('assignment_date')->nullable()->after('enquiry_officer_id');
            $table->dateTime('submitted_at')->nullable()->after('status');
            $table->dateTime('approved_at')->nullable()->after('submitted_at');
            $table->foreignId('case_file_id')->nullable()->constrained('cases')->nullOnDelete()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn(['cfr_summary', 'reg_date', 'assignment_date', 'submitted_at', 'approved_at']);
        });
    }
};

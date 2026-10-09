<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_officer_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 20)->default('assigned');
            $table->string('recommendation', 30)->nullable();
            $table->text('report_text')->nullable();
            $table->string('closure_reason', 30)->nullable();
            $table->foreignId('merge_complaint_id')->nullable()->constrained('complaints')->nullOnDelete();
            $table->string('transfer_department')->nullable();
            $table->string('transfer_circle')->nullable();
            $table->string('priority_type', 30)->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};

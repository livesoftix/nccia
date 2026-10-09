<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When an officer is reassigned, keep a dated record of who worked
     * on the matter and a snapshot of their work (for accountability).
     */
    public function up(): void
    {
        Schema::create('officer_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type'); // verification | enquiry | case
            $table->unsignedBigInteger('subject_id');
            $table->string('officer_role', 40); // verification_officer | enquiry_officer | investigation_officer
            $table->foreignId('officer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->foreignId('unassigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason')->nullable();
            $table->json('work_snapshot')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'oah_subject_idx');
            $table->index(['officer_id', 'assigned_at'], 'oah_officer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_assignment_histories');
    }
};

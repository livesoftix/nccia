<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes so lists/dashboard/search stay fast at multi-million row scale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->index(['circle_id', 'status', 'created_at'], 'complaints_circle_status_created_idx');
            $table->index(['operator_id', 'status'], 'complaints_operator_status_idx');
            $table->index(['user_id', 'status'], 'complaints_user_status_idx');
            $table->index(['status', 'created_at'], 'complaints_status_created_idx');
            $table->index(['cnic'], 'complaints_cnic_idx');
            $table->index(['diary_no'], 'complaints_diary_no_idx');
        });

        Schema::table('verifications', function (Blueprint $table) {
            $table->index(['verification_officer_id', 'status', 'assigned_at'], 'verifications_vo_status_assigned_idx');
            $table->index(['status', 'submitted_at'], 'verifications_status_submitted_idx');
            $table->index(['complaint_id', 'status'], 'verifications_complaint_status_idx');
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->index(['enquiry_officer_id', 'status'], 'enquiries_eo_status_idx');
            $table->index(['complaint_id', 'status'], 'enquiries_complaint_status_idx');
            $table->index(['status', 'submitted_at'], 'enquiries_status_submitted_idx');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->index(['investigation_officer_id', 'status'], 'cases_io_status_idx');
            $table->index(['enquiry_id', 'status'], 'cases_enquiry_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex('complaints_circle_status_created_idx');
            $table->dropIndex('complaints_operator_status_idx');
            $table->dropIndex('complaints_user_status_idx');
            $table->dropIndex('complaints_status_created_idx');
            $table->dropIndex('complaints_cnic_idx');
            $table->dropIndex('complaints_diary_no_idx');
        });

        Schema::table('verifications', function (Blueprint $table) {
            $table->dropIndex('verifications_vo_status_assigned_idx');
            $table->dropIndex('verifications_status_submitted_idx');
            $table->dropIndex('verifications_complaint_status_idx');
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex('enquiries_eo_status_idx');
            $table->dropIndex('enquiries_complaint_status_idx');
            $table->dropIndex('enquiries_status_submitted_idx');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex('cases_io_status_idx');
            $table->dropIndex('cases_enquiry_status_idx');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (Schema::hasColumn('complaints', 'final_status')) {
                $table->index(['final_status', 'created_at'], 'complaints_final_status_created_idx');
                $table->index(['circle_id', 'final_status'], 'complaints_circle_final_status_idx');
            }
            if (Schema::hasColumn('complaints', 'offence_type')) {
                $table->index(['offence_type', 'created_at'], 'complaints_offence_created_idx');
            }
            if (Schema::hasColumn('complaints', 'enquiry_id')) {
                $table->index(['enquiry_id'], 'complaints_enquiry_id_idx');
            }
            if (Schema::hasColumn('complaints', 'created_at')) {
                $table->index(['created_at'], 'complaints_created_at_idx');
            }
        });

        Schema::table('verifications', function (Blueprint $table) {
            if (Schema::hasColumn('verifications', 'created_at')) {
                $table->index(['created_at'], 'verifications_created_at_idx');
            }
            if (Schema::hasColumn('verifications', 'verification_officer_id')) {
                $table->index(['verification_officer_id', 'created_at'], 'verifications_vo_created_idx');
            }
        });

        Schema::table('enquiries', function (Blueprint $table) {
            if (Schema::hasColumn('enquiries', 'circle_id')) {
                $table->index(['circle_id', 'status'], 'enquiries_circle_status_idx');
            }
            if (Schema::hasColumn('enquiries', 'created_at')) {
                $table->index(['created_at'], 'enquiries_created_at_idx');
            }
            if (Schema::hasColumn('enquiries', 'enquiry_officer_id')) {
                $table->index(['enquiry_officer_id', 'created_at'], 'enquiries_eo_created_idx');
            }
        });

        if (Schema::hasTable('verification_reports')) {
            Schema::table('verification_reports', function (Blueprint $table) {
                if (Schema::hasColumn('verification_reports', 'complaint_id')) {
                    $table->index(['complaint_id'], 'v_reports_complaint_id_idx');
                }
                if (Schema::hasColumn('verification_reports', 'tracking_no')) {
                    $table->index(['tracking_no'], 'v_reports_tracking_no_idx');
                }
                if (Schema::hasColumn('verification_reports', 'created_by')) {
                    $table->index(['created_by', 'created_at'], 'v_reports_creator_created_idx');
                }
            });
        }

        if (Schema::hasTable('login_histories')) {
            Schema::table('login_histories', function (Blueprint $table) {
                if (Schema::hasColumn('login_histories', 'real_ip')) {
                    $table->index(['real_ip'], 'login_histories_real_ip_idx');
                }
                if (Schema::hasColumn('login_histories', 'login_method')) {
                    $table->index(['login_method'], 'login_histories_method_idx');
                }
                if (Schema::hasColumn('login_histories', 'is_spoofed')) {
                    $table->index(['is_spoofed', 'logged_in_at'], 'login_histories_spoofed_date_idx');
                }
            });
        }
    }

    public function down(): void
    {
        // Safe rollback
    }
};

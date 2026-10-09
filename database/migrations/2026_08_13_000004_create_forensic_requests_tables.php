<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forensic_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique(); // FR-...
            $table->foreignId('enquiry_id')->nullable()->constrained('enquiries')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('destination', 20); // forensic | technical
            $table->text('note')->nullable();
            $table->string('status', 30)->default('submitted');
            // submitted → assigned → in_progress → report_ready → handed_over
            $table->string('report_code')->nullable()->unique();
            $table->foreignId('ad_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ad_reviewed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // FO
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('opened_at')->nullable(); // FO first open → code generated
            $table->timestamp('report_ready_at')->nullable();
            $table->foreignId('desk_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('desk_notified_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->foreignId('handed_to_user_id')->nullable()->constrained('users')->nullOnDelete(); // EO
            $table->text('handover_remarks')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['destination', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('forensic_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forensic_request_id')->constrained('forensic_requests')->cascadeOnDelete();
            $table->string('item_type', 50); // phone | laptop | sim | hdd | other
            $table->string('make_model')->nullable();
            $table->string('imei')->nullable();
            $table->string('serial_no')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forensic_request_items');
        Schema::dropIfExists('forensic_requests');
    }
};

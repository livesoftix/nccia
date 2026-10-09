<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsr_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->date('report_date');
            $table->string('unit_name')->default('CCRC-Lahore');
            $table->string('status')->default('draft');
            $table->json('highlights')->nullable();
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('compiled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_to_ci_at')->nullable();
            $table->foreignId('ci_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ci_reviewed_at')->nullable();
            $table->text('ci_remarks')->nullable();
            $table->timestamp('forwarded_to_hq_at')->nullable();
            $table->foreignId('forwarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hq_acknowledged_at')->nullable();
            $table->foreignId('hq_acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('send_back_remarks')->nullable();
            $table->timestamps();

            $table->unique(['circle_id', 'report_date']);
            $table->index(['status', 'report_date']);
        });

        Schema::create('do_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->date('report_month');
            $table->string('month_label');
            $table->string('status')->default('draft');
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('compiled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_to_ci_at')->nullable();
            $table->foreignId('ci_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ci_reviewed_at')->nullable();
            $table->text('ci_remarks')->nullable();
            $table->timestamp('forwarded_to_hq_at')->nullable();
            $table->foreignId('forwarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hq_acknowledged_at')->nullable();
            $table->foreignId('hq_acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('send_back_remarks')->nullable();
            $table->timestamps();

            $table->unique(['circle_id', 'report_month']);
            $table->index(['status', 'report_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('do_letters');
        Schema::dropIfExists('dsr_reports');
    }
};

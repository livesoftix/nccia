<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_no')->unique()->nullable();

            // Step 1 — Complainant Details
            $table->string('complainant_name');
            $table->string('cnic', 15);
            $table->string('contact_no');
            $table->text('address');
            $table->string('profession')->nullable();

            // Step 2 — Complaint Details
            $table->date('report_date');
            $table->string('diary_no');
            $table->string('received_via');
            $table->string('received_from');
            $table->string('cmu')->nullable();
            $table->string('priority_type')->default('regular');

            // Step 3 — Crime Details
            $table->string('offence_type');
            $table->decimal('amount_involved', 14, 2)->nullable();
            $table->date('occurrence_date');
            $table->json('laws')->nullable();
            $table->text('description');
            $table->json('evidence')->nullable();

            // Step 4 — Operator Details
            $table->string('operator_name');
            $table->string('operator_designation');
            $table->datetime('entry_time');
            $table->string('scrutiny_result');
            $table->text('operator_remarks')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};

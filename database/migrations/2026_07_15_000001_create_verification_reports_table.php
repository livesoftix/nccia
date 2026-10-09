<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('complaint_id')->nullable()->constrained('complaints')->nullOnDelete();
            $table->string('tracking_no');
            $table->date('assignment_date')->nullable();
            $table->date('verification_date')->nullable();

            // Victim data
            $table->string('victim_name');
            $table->string('victim_father_name')->nullable();
            $table->string('victim_occupation')->nullable();
            $table->string('victim_gender')->nullable();
            $table->string('victim_cnic');
            $table->string('victim_country_code', 8)->nullable();
            $table->string('victim_phone');

            // Crime
            $table->string('crime_category');
            $table->text('crime_description')->nullable();
            $table->string('city');

            // Accused
            $table->boolean('accused_known')->default(false);
            $table->json('accused')->nullable();

            // Recommendations
            $table->text('recommendation_short')->nullable();
            $table->text('recommendation_full')->nullable();

            // Evidence
            $table->json('evidence')->nullable();

            // Inquiry / case
            $table->string('inquiry_no')->nullable();
            $table->string('case_no')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('court_name');
            $table->string('judge_name')->nullable();
            $table->date('filing_date');
            $table->string('status', 20)->default('filed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_cases');
    }
};

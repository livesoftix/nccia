<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_case_id')->constrained()->cascadeOnDelete();
            $table->string('report_type', 20);
            $table->string('file_path');
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_reports');
    }
};

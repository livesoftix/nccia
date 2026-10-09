<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_hearings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_case_id')->constrained()->cascadeOnDelete();
            $table->date('hearing_date');
            $table->string('type', 30);
            $table->text('notes')->nullable();
            $table->date('next_hearing_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_hearings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_verdicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_case_id')->constrained()->cascadeOnDelete();
            $table->string('verdict', 15);
            $table->date('verdict_date');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_verdicts');
    }
};

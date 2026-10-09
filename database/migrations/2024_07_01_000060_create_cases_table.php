<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->string('fir_no')->unique();
            $table->foreignId('investigation_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('registered');
            $table->string('recommendation', 30)->nullable();
            $table->string('transfer_department')->nullable();
            $table->string('transfer_circle')->nullable();
            $table->foreignId('merge_complaint_id')->nullable()->constrained('complaints')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};

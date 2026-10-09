<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('circle_incharge_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 10);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_approvals');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_officers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('badge_no')->unique();
            $table->string('designation')->nullable();
            $table->string('circle')->nullable();
            $table->string('zone')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->string('status')->default('active');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_officers');
    }
};

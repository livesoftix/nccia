<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_manuals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('audience');
            $table->string('version', 20)->default('1.0');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_manuals');
    }
};

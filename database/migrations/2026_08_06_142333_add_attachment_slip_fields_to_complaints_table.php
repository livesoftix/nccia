<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            // Attachment field for complaint
            $table->string('attachment')->nullable();
            
            // Slip generation fields
            $table->boolean('slip_generated')->default(false);
            $table->datetime('slip_generated_at')->nullable();
            $table->string('slip_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['attachment', 'slip_generated', 'slip_generated_at', 'slip_number']);
        });
    }
};

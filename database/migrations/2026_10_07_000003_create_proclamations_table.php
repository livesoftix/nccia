<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proclamation for an absconding accused — Section 87, CrPC 1898.
 * Raised on a registered case (post-FIR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proclamations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_file_id');
            $table->string('fir_no')->nullable();            // snapshot for the printed document
            $table->string('accused_name');
            $table->string('accused_father_name')->nullable();
            $table->text('accused_address')->nullable();
            $table->text('offence')->nullable();
            $table->string('court_name')->nullable();
            $table->date('proclaimed_on')->nullable();        // date the proclamation is published
            $table->date('appear_by_date');                   // must be >= 30 days from proclaimed_on (s.87)
            $table->string('publication_place')->nullable();  // where read/affixed
            $table->enum('status', ['draft', 'issued', 'appeared', 'absconder'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->index('case_file_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proclamations');
    }
};

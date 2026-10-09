<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiry_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained('enquiries')->cascadeOnDelete();

            $table->string('notice_number')->nullable();
            $table->string('notice_type')->nullable();          // summon, warning, final, etc.
            $table->string('receiver_name')->nullable();
            $table->string('person_type')->nullable();          // complainant, accused, witness
            $table->string('notice_via')->nullable();            // whatsapp, sms, email, phone, fax, postal, call
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->date('notice_date')->nullable();
            $table->text('description')->nullable();

            // Appearance tracking
            $table->string('status')->default('issued');         // issued, served, unserved, non_appearance
            $table->dateTime('served_at')->nullable();
            $table->dateTime('appeared_at')->nullable();

            // Verification QR token
            $table->string('verification_token', 64)->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_notices');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable, tamper-evident audit ledger. Each entry chains to the previous
 * one by hash (like a private blockchain), so any later alteration or deletion
 * breaks the chain and is detectable. Append-only: the app never updates or
 * deletes rows here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_ledger', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event');                          // e.g. complaint.created, enquiry.forwarded, login
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('subject_type')->nullable();       // model class
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->json('properties')->nullable();           // changed fields / context
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->char('data_hash', 64);                    // hash of this entry's data
            $table->char('prev_hash', 64);                    // hash of the previous entry
            $table->char('hash', 64)->unique();               // chain hash = H(prev_hash + data_hash + ts)
            $table->timestamp('created_at')->nullable();

            $table->index('event');
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_ledger');
    }
};

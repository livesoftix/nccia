<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An officer's request for a warrant / proclamation / attachment that needs
 * Circle Incharge approval before it can be issued or printed (used only when
 * the matching approval_setting is "mandatory").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warrant_requests', function (Blueprint $table) {
            $table->id();
            $table->string('action_key');              // arrest_warrant, search_warrant, raid_permission, proclamation, attachment
            $table->unsignedBigInteger('enquiry_id')->nullable();
            $table->unsignedBigInteger('case_file_id')->nullable();
            $table->unsignedBigInteger('circle_id')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('remarks')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['action_key', 'status']);
            $table->index(['enquiry_id']);
            $table->index(['case_file_id']);
            $table->index(['circle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warrant_requests');
    }
};

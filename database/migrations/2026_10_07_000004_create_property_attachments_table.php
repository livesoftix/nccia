<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attachment of property of a proclaimed (absconding) person — Section 88, CrPC 1898.
 * Linked to a proclamation raised under Section 87.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proclamation_id');
            $table->unsignedBigInteger('case_file_id');
            $table->enum('property_type', ['movable', 'immovable', 'both'])->default('movable');
            $table->text('property_description');
            $table->string('location')->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->date('attachment_date')->nullable();
            $table->string('order_no')->nullable();
            $table->enum('status', ['draft', 'attached', 'released', 'sold'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('proclamation_id');
            $table->index('case_file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_attachments');
    }
};

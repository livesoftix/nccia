<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_pdf_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_id')->nullable()->index();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('inquiry_ref')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->json('extracted_data')->nullable();
            $table->json('import_result')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('complaint_id')->nullable()->constrained('complaints')->nullOnDelete();
            $table->foreignId('verification_report_id')->nullable()->constrained('verification_reports')->nullOnDelete();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->boolean('used_ocr')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_pdf_imports');
    }
};

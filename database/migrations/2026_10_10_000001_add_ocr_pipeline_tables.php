<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circle-scoped, checkpointed OCR pipeline. Additive only: existing import rows
 * keep working (circle_id/file_hash stay NULL for legacy imports).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaint_pdf_imports', function (Blueprint $table) {
            // The circle chosen (and authorized) at upload time. OCR output never changes it.
            $table->foreignId('circle_id')->nullable()->after('user_id')->constrained('circles')->restrictOnDelete();
            $table->char('file_hash', 64)->nullable()->after('stored_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_hash');
            $table->unsignedInteger('pages_done')->default(0)->after('page_count');
            $table->unsignedSmallInteger('attempts')->default(0)->after('pages_done');
            $table->string('layout', 60)->nullable()->after('attempts');
            $table->json('field_results')->nullable()->after('extracted_data');
            $table->json('review_reasons')->nullable()->after('field_results');
            $table->string('circle_hint', 120)->nullable()->after('review_reasons');
            $table->decimal('mean_confidence', 5, 4)->nullable()->after('circle_hint');
            $table->foreignId('reviewed_by')->nullable()->after('mean_confidence')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            // Duplicate detection is per circle: the same file uploaded twice into one
            // circle maps to one import; other circles never learn that it exists.
            $table->unique(['circle_id', 'file_hash'], 'pdf_imports_circle_hash_unique');
            $table->index(['circle_id', 'status'], 'pdf_imports_circle_status_idx');
        });

        Schema::create('complaint_pdf_import_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_pdf_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_no');
            $table->string('method', 16);            // text | ocr | browser_ocr
            $table->longText('text')->nullable();
            $table->decimal('confidence', 5, 4)->default(0);
            $table->unsignedSmallInteger('rotation')->default(0);
            $table->decimal('skew', 6, 2)->default(0);
            $table->string('engine', 30)->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('ms')->default(0);
            $table->timestamps();

            // Checkpoint key: reprocessing a page overwrites, never duplicates.
            $table->unique(['complaint_pdf_import_id', 'page_no'], 'pdf_import_pages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_pdf_import_pages');
        Schema::table('complaint_pdf_imports', function (Blueprint $table) {
            $table->dropUnique('pdf_imports_circle_hash_unique');
            $table->dropIndex('pdf_imports_circle_status_idx');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('circle_id');
            $table->dropColumn(['file_hash', 'file_size', 'pages_done', 'attempts', 'layout', 'field_results',
                'review_reasons', 'circle_hint', 'mean_confidence', 'reviewed_at']);
        });
    }
};

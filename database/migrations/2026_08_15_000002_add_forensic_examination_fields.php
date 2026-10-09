<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forensic_requests', function (Blueprint $table) {
            $table->string('priority', 20)->default('normal')->after('status');
            $table->text('findings')->nullable()->after('note');
            $table->text('lab_notes')->nullable()->after('findings');
            $table->string('report_attachment_path')->nullable()->after('attachment_path');
        });

        Schema::table('forensic_request_items', function (Blueprint $table) {
            $table->string('imei2', 64)->nullable()->after('imei');
            $table->string('storage_capacity', 64)->nullable()->after('serial_no');
            $table->string('condition', 100)->nullable()->after('storage_capacity');
            $table->string('seized_from', 255)->nullable()->after('condition');
        });
    }

    public function down(): void
    {
        Schema::table('forensic_requests', function (Blueprint $table) {
            $table->dropColumn(['priority', 'findings', 'lab_notes', 'report_attachment_path']);
        });

        Schema::table('forensic_request_items', function (Blueprint $table) {
            $table->dropColumn(['imei2', 'storage_capacity', 'condition', 'seized_from']);
        });
    }
};

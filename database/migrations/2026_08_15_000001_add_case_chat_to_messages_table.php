<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('receiver_id')->nullable()->change();
            $table->foreignId('enquiry_id')->nullable()->after('receiver_id')->constrained('enquiries')->nullOnDelete();
            $table->foreignId('case_file_id')->nullable()->after('enquiry_id')->constrained('cases')->nullOnDelete();
            $table->string('case_number')->nullable()->after('case_file_id');

            $table->index(['enquiry_id', 'created_at']);
            $table->index(['case_file_id', 'created_at']);
            $table->index('case_number');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['enquiry_id']);
            $table->dropForeign(['case_file_id']);
            $table->dropColumn(['enquiry_id', 'case_file_id', 'case_number']);
            $table->foreignId('receiver_id')->nullable(false)->change();
        });
    }
};

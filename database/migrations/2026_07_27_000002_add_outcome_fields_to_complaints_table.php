<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('closure_reason', 30)->nullable()->after('final_status');
            $table->foreignId('merged_with_id')->nullable()->constrained('complaints')->nullOnDelete()->after('closure_reason');
            $table->string('transfer_to_department')->nullable()->after('merged_with_id');
            $table->foreignId('transfer_to_circle_id')->nullable()->constrained('circles')->nullOnDelete()->after('transfer_to_department');
            $table->foreignId('enquiry_id')->nullable()->constrained('enquiries')->nullOnDelete()->after('transfer_to_circle_id');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enquiry_id');
            $table->dropConstrainedForeignId('transfer_to_circle_id');
            $table->dropConstrainedForeignId('merged_with_id');
            $table->dropColumn(['closure_reason', 'transfer_to_department']);
        });
    }
};

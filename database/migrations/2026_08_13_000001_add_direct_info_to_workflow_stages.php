<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VIP / direct / departmental cases can enter the workflow without a
     * registered complaint. Each stage carries its own snapshot of the
     * reference number and complainant in a single JSON `direct_info` column.
     */
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->json('direct_info')->nullable()->after('complaint_id');
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->json('direct_info')->nullable()->after('complaint_id');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->json('direct_info')->nullable()->after('enquiry_id');
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropColumn('direct_info');
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn('direct_info');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('direct_info');
        });
    }
};
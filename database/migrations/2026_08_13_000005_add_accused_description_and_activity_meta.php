<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('enquiry_accused') && !Schema::hasColumn('enquiry_accused', 'description')) {
            Schema::table('enquiry_accused', function (Blueprint $table) {
                $table->text('description')->nullable()->after('designation');
            });
        }

        if (Schema::hasTable('enquiry_activities') && !Schema::hasColumn('enquiry_activities', 'meta')) {
            Schema::table('enquiry_activities', function (Blueprint $table) {
                $table->json('meta')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('enquiry_accused') && Schema::hasColumn('enquiry_accused', 'description')) {
            Schema::table('enquiry_accused', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }

        if (Schema::hasTable('enquiry_activities') && Schema::hasColumn('enquiry_activities', 'meta')) {
            Schema::table('enquiry_activities', function (Blueprint $table) {
                $table->dropColumn('meta');
            });
        }
    }
};

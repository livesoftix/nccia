<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiry_notices', function (Blueprint $table) {
            if (!Schema::hasColumn('enquiry_notices', 'father_name')) {
                $table->string('father_name')->nullable()->after('receiver_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiry_notices', function (Blueprint $table) {
            if (Schema::hasColumn('enquiry_notices', 'father_name')) {
                $table->dropColumn('father_name');
            }
        });
    }
};

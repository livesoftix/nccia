<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiry_notices', function (Blueprint $table) {
            $table->string('cnic')->nullable()->after('receiver_name');
        });
    }

    public function down(): void
    {
        Schema::table('enquiry_notices', function (Blueprint $table) {
            $table->dropColumn('cnic');
        });
    }
};

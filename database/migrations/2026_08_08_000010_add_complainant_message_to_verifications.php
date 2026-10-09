<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dateTime('appeared_at')->nullable()->after('completed_at');
            $table->text('complainant_message')->nullable()->after('report_text');
            $table->unsignedBigInteger('sent_by')->nullable()->after('complainant_message');
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropForeign(['sent_by']);
            $table->dropColumn(['appeared_at', 'complainant_message', 'sent_by']);
        });
    }
};

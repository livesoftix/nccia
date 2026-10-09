<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            if (!Schema::hasColumn('verifications', 'message_via')) {
                $table->string('message_via', 50)->nullable()->after('complainant_message');
            }
            if (!Schema::hasColumn('verifications', 'whatsapp_sent_at')) {
                $table->dateTime('whatsapp_sent_at')->nullable()->after('message_via');
            }
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('verifications', 'message_via')) {
                $cols[] = 'message_via';
            }
            if (Schema::hasColumn('verifications', 'whatsapp_sent_at')) {
                $cols[] = 'whatsapp_sent_at';
            }
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};

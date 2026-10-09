<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('complaints', 'registration_message')) {
                $table->text('registration_message')->nullable()->after('operator_remarks');
            }
            if (!Schema::hasColumn('complaints', 'registration_notify_via')) {
                $table->string('registration_notify_via', 50)->nullable()->after('registration_message');
            }
            if (!Schema::hasColumn('complaints', 'registration_notified_at')) {
                $table->dateTime('registration_notified_at')->nullable()->after('registration_notify_via');
            }
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('complaints', 'registration_message') ? 'registration_message' : null,
                Schema::hasColumn('complaints', 'registration_notify_via') ? 'registration_notify_via' : null,
                Schema::hasColumn('complaints', 'registration_notified_at') ? 'registration_notified_at' : null,
            ]);
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};

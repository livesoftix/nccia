<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('verification_reports', 'registration_at')) {
                $table->dateTime('registration_at')->nullable()->after('tracking_no');
            }
            if (!Schema::hasColumn('verification_reports', 'comments')) {
                $table->text('comments')->nullable()->after('recommendation_full');
            }
        });

        // Promote date-only columns to datetime so time can be stored.
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE verification_reports MODIFY assignment_date DATETIME NULL');
            DB::statement('ALTER TABLE verification_reports MODIFY verification_date DATETIME NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite stores datetimes as text; existing date values remain valid.
        } else {
            Schema::table('verification_reports', function (Blueprint $table) {
                $table->dateTime('assignment_date')->nullable()->change();
                $table->dateTime('verification_date')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('verification_reports', function (Blueprint $table) {
            if (Schema::hasColumn('verification_reports', 'registration_at')) {
                $table->dropColumn('registration_at');
            }
            if (Schema::hasColumn('verification_reports', 'comments')) {
                $table->dropColumn('comments');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE verification_reports MODIFY assignment_date DATE NULL');
            DB::statement('ALTER TABLE verification_reports MODIFY verification_date DATE NULL');
        }
    }
};

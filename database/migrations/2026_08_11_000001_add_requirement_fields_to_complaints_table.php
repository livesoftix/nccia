<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('complaints', 'father_name')) {
                $table->string('father_name')->nullable()->after('complainant_name');
            }
            if (!Schema::hasColumn('complaints', 'whatsapp_no')) {
                $table->string('whatsapp_no', 20)->nullable()->after('contact_no');
            }
            if (!Schema::hasColumn('complaints', 'gender')) {
                $table->string('gender', 20)->nullable()->after('whatsapp_no');
            }
            if (!Schema::hasColumn('complaints', 'email')) {
                $table->string('email')->nullable()->after('gender');
            }
            if (!Schema::hasColumn('complaints', 'district')) {
                $table->string('district')->nullable()->after('post_address');
            }
            if (!Schema::hasColumn('complaints', 'reporting_time')) {
                $table->dateTime('reporting_time')->nullable()->after('report_date');
            }
            if (!Schema::hasColumn('complaints', 'platforms')) {
                $table->json('platforms')->nullable()->after('description');
            }
            if (!Schema::hasColumn('complaints', 'platform_profile_page')) {
                $table->string('platform_profile_page')->nullable()->after('platforms');
            }
            if (!Schema::hasColumn('complaints', 'platform_username')) {
                $table->string('platform_username')->nullable()->after('platform_profile_page');
            }
            if (!Schema::hasColumn('complaints', 'platform_email_involved')) {
                $table->string('platform_email_involved')->nullable()->after('platform_username');
            }
            if (!Schema::hasColumn('complaints', 'platform_mobile_involved')) {
                $table->string('platform_mobile_involved', 30)->nullable()->after('platform_email_involved');
            }
            if (!Schema::hasColumn('complaints', 'crime_mediums')) {
                $table->json('crime_mediums')->nullable()->after('offence_type');
            }
            if (!Schema::hasColumn('complaints', 'bank_name_sender')) {
                $table->string('bank_name_sender')->nullable()->after('amount_involved');
            }
            if (!Schema::hasColumn('complaints', 'bank_name_receiver')) {
                $table->string('bank_name_receiver')->nullable()->after('bank_name_sender');
            }
            if (!Schema::hasColumn('complaints', 'account_no_sender')) {
                $table->string('account_no_sender')->nullable()->after('bank_name_receiver');
            }
            if (!Schema::hasColumn('complaints', 'account_no_receiver')) {
                $table->string('account_no_receiver')->nullable()->after('account_no_sender');
            }
            if (!Schema::hasColumn('complaints', 'transaction_date')) {
                $table->date('transaction_date')->nullable()->after('account_no_receiver');
            }
            if (!Schema::hasColumn('complaints', 'initial_accused')) {
                $table->json('initial_accused')->nullable()->after('evidence');
            }
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $cols = [
                'father_name', 'whatsapp_no', 'gender', 'email', 'district', 'reporting_time',
                'platforms', 'platform_profile_page', 'platform_username', 'platform_email_involved',
                'platform_mobile_involved', 'crime_mediums', 'bank_name_sender', 'bank_name_receiver',
                'account_no_sender', 'account_no_receiver', 'transaction_date', 'initial_accused',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('complaints', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

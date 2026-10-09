<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forensic_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('forensic_requests', 'is_external')) {
                $table->boolean('is_external')->default(false)->after('status');
            }
            if (!Schema::hasColumn('forensic_requests', 'external_ref')) {
                $table->string('external_ref')->nullable()->after('is_external');
            }
            if (!Schema::hasColumn('forensic_requests', 'external_letter_no')) {
                $table->string('external_letter_no')->nullable()->after('external_ref');
            }
            if (!Schema::hasColumn('forensic_requests', 'external_courier_no')) {
                $table->string('external_courier_no')->nullable()->after('external_letter_no');
            }
            if (!Schema::hasColumn('forensic_requests', 'external_organization')) {
                $table->string('external_organization')->nullable()->after('external_courier_no');
            }
            if (!Schema::hasColumn('forensic_requests', 'external_person_name')) {
                $table->string('external_person_name')->nullable()->after('external_organization');
            }
            if (!Schema::hasColumn('forensic_requests', 'external_person_address')) {
                $table->string('external_person_address')->nullable()->after('external_person_name');
            }
            if (!Schema::hasColumn('forensic_requests', 'external_person_contact')) {
                $table->string('external_person_contact')->nullable()->after('external_person_address');
            }
            if (!Schema::hasColumn('forensic_requests', 'external_category')) {
                $table->string('external_category')->nullable()->after('external_person_contact');
            }
            if (!Schema::hasColumn('forensic_requests', 'external_scope')) {
                $table->text('external_scope')->nullable()->after('external_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forensic_requests', function (Blueprint $table) {
            $columns = [
                'is_external',
                'external_ref',
                'external_letter_no',
                'external_courier_no',
                'external_organization',
                'external_person_name',
                'external_person_address',
                'external_person_contact',
                'external_category',
                'external_scope',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('forensic_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

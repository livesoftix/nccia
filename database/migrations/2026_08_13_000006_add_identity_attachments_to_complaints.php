<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('complaints')) {
            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('complaints', 'cnic_front')) {
                $table->string('cnic_front')->nullable()->after('attachment');
            }
            if (!Schema::hasColumn('complaints', 'cnic_back')) {
                $table->string('cnic_back')->nullable()->after('cnic_front');
            }
            if (!Schema::hasColumn('complaints', 'passport_attachment')) {
                $table->string('passport_attachment')->nullable()->after('cnic_back');
            }
            if (!Schema::hasColumn('complaints', 'picture')) {
                $table->string('picture')->nullable()->after('passport_attachment');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('complaints')) {
            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            foreach (['cnic_front', 'cnic_back', 'passport_attachment', 'picture'] as $col) {
                if (Schema::hasColumn('complaints', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE complaint_pdf_imports MODIFY stored_path TEXT NOT NULL');
        }

        Schema::table('complaint_pdf_imports', function (Blueprint $table) {
            $table->index('original_filename');
        });
    }

    public function down(): void
    {
        Schema::table('complaint_pdf_imports', function (Blueprint $table) {
            $table->dropIndex(['original_filename']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE complaint_pdf_imports MODIFY stored_path VARCHAR(255) NOT NULL');
        }
    }
};

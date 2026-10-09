<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('complaints', function (Blueprint $table) {
            $table->index('cnic');
            $table->index('status');
            $table->index('complainant_name');
            $table->index('created_at');
            $table->index('diary_no');
        });
    }

    public function down(): void {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['cnic']);
            $table->dropIndex(['status']);
            $table->dropIndex(['complainant_name']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['diary_no']);
        });
    }
};


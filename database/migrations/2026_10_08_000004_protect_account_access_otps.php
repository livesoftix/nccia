<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->string('otp_hash', 128)->nullable();
            $table->unsignedBigInteger('officer_id')->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
        });
        // Legacy codes were unbound and stored in plaintext; do not grandfather them.
        DB::table('otps')->update(['otp' => '', 'expires_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropColumn(['otp_hash', 'officer_id', 'attempts']);
        });
    }
};

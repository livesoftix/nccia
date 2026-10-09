<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('ip_address', 45);
            $table->string('real_ip', 45)->nullable();
            $table->json('proxy_headers')->nullable();
            $table->boolean('is_spoofed')->default(false);
            $table->text('user_agent')->nullable();
            $table->string('login_method', 20)->default('web');
            $table->timestamp('logged_in_at')->useCurrent();
            $table->timestamp('logged_out_at')->nullable();
            $table->index(['user_id', 'logged_in_at']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
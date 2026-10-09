<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::where('email', 'admin@admin.com')->first();
        if ($admin) {
            $admin->syncRoles(['admin']);
            $admin->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        $admin = User::where('email', 'admin@admin.com')->first();
        if ($admin) {
            $admin->syncRoles(['operator']);
            $admin->update(['role' => 'operator']);
        }
    }
};

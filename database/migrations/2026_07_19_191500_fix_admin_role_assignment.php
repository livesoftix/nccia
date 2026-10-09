<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure admin role exists
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Fix admin@admin.com user
        $admin = User::where('email', 'admin@admin.com')->first();
        if ($admin) {
            $admin->syncRoles(['admin']);
            $admin->update(['role' => 'admin']);
        }

        // Fix any user whose email starts with admin
        $users = User::where('email', 'like', 'admin%')->get();
        foreach ($users as $user) {
            $user->syncRoles(['admin']);
            if ($user->role !== 'admin') {
                $user->update(['role' => 'admin']);
            }
        }
    }

    public function down(): void
    {
        // No rollback needed
    }
};

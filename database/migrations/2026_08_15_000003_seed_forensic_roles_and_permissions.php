<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure cache is cleared
        try {
            $cacheKey = config('permission.cache.key');
            $cacheStore = config('permission.cache.store');
            app('cache')->store($cacheStore != 'default' ? $cacheStore : null)->forget($cacheKey);
        } catch (\Throwable $e) {}

        // Ensure permissions exist
        $permissions = [
            'forensic',
            'forensic_users',
            'dashboard',
            'enquiries',
            'complaints',
            'profile',
        ];

        foreach ($permissions as $perm) {
            try {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            } catch (\Throwable $e) {}
        }

        // Ensure forensic roles exist
        $roles = [
            'admin_forensic' => ['forensic', 'forensic_users', 'profile'],
            'ad_forensic'    => ['forensic', 'profile'],
            'desk_forensic'  => ['forensic', 'profile'],
            'forensic_team'  => ['forensic', 'profile'],
        ];

        foreach ($roles as $roleName => $perms) {
            try {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                $role->syncPermissions($perms);
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Clear permission cache
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

        // Create dd_forensic role
        try {
            $role = Role::firstOrCreate(['name' => 'dd_forensic', 'guard_name' => 'web']);
            $role->syncPermissions(['forensic', 'profile']);
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
    }
};

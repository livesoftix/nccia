<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $cacheKey = config('permission.cache.key');
        $cacheStore = config('permission.cache.store');
        app('cache')->store($cacheStore != 'default' ? $cacheStore : null)->forget($cacheKey);

        $features = [
            'dashboard', 'analytics', 'department_progress', 'complaints', 'verifications',
            'reports', 'enquiries', 'io_records', 'dac_cases',
            'court_cases', 'users', 'circles', 'offence_types',
            'reference', 'sms_logs', 'profile', 'login_history',
            'forensic', 'forensic_users', 'dsr_reports', 'do_letters',
        ];

        foreach ($features as $feat) {
            Permission::firstOrCreate(['name' => $feat, 'guard_name' => 'web']);
        }

        // NCCIA flowchart stages: Complaint → Verification → Enquiry → Case → Court
        $roleFeatures = [
            'admin' => [
                'dashboard','analytics','department_progress','complaints','verifications','reports','enquiries',
                'io_records','dac_cases','court_cases','users','circles','offence_types','reference','sms_logs','profile','login_history',
                'dsr_reports','do_letters',
            ],
            // Assign VO/EO/IO, approve reports/CFR/cases
            'circle_incharge' => [
                'dashboard','analytics','complaints','verifications','reports','enquiries',
                'io_records','dac_cases','court_cases','offence_types','reference','sms_logs','profile',
                'dsr_reports','do_letters',
            ],
            // Front Desk Officer / CMU: Complete Registration only (+ assign VO on that form)
            'front_desk_officer' => [
                'dashboard','complaints','profile',
            ],
            'operator' => [
                'dashboard','complaints','profile',
            ],
            // Verification stage only
            'verification_officer' => [
                'dashboard','verifications','reports','profile',
            ],
            // Enquiry stage (+ DAC request during enquiry)
            'enquiry_officer' => [
                'dashboard','enquiries','dac_cases','profile',
            ],
            // Case / Court stage
            'investigation_officer' => [
                'dashboard','dac_cases','court_cases','profile',
            ],
            // FIR number / case registry
            'moharrar' => [
                'dashboard','dac_cases','court_cases','profile',
            ],
            // Enquiry number registration
            'reader_branch' => [
                'dashboard','enquiries','profile',
            ],
            'ad_legal' => ['dashboard','department_progress','enquiries','dac_cases','profile'],
            'dd_legal' => ['dashboard','department_progress','enquiries','dac_cases','profile'],
            'additional_director' => ['dashboard','department_progress','enquiries','dac_cases','profile'],
            'director_general' => [
                'dashboard','analytics','department_progress','complaints','verifications','reports','enquiries',
                'io_records','dac_cases','court_cases','users','circles','offence_types','reference','sms_logs','profile','login_history',
                'dsr_reports','do_letters',
            ],
            // Assistant Director Administration — compiles DSR & D.O. Letter
            'ad_administration' => ['dashboard','dsr_reports','do_letters','profile'],
            // Forensic portal roles — isolated from the main NCCIA modules
            'admin_forensic' => ['forensic', 'forensic_users', 'profile'],
            'dd_forensic'    => ['forensic', 'profile'],
            'ad_forensic'    => ['forensic', 'profile'],
        ];

        foreach ($roleFeatures as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        foreach (User::role('verification_officer')->get() as $user) {
            $user->revokePermissionTo(['enquiries', 'complaints', 'dac_cases', 'court_cases', 'analytics', 'io_records']);
        }
        foreach (User::role('operator')->get() as $user) {
            $user->revokePermissionTo([
                'enquiries', 'dac_cases', 'court_cases', 'io_records',
                'verifications', 'reports', 'analytics',
            ]);
        }
        foreach (User::role('investigation_officer')->get() as $user) {
            $user->revokePermissionTo(['verifications', 'reports', 'enquiries', 'complaints']);
        }
        foreach (User::role('moharrar')->get() as $user) {
            $user->revokePermissionTo(['complaints', 'enquiries', 'verifications', 'reports']);
        }
        foreach (User::role('reader_branch')->get() as $user) {
            $user->revokePermissionTo(['complaints', 'verifications', 'reports', 'dac_cases', 'court_cases']);
        }
    }
}

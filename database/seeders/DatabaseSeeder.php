<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        require_once __DIR__ . '/CircleTenancySeeder.php';

        $this->call([
            ProfessionSeeder::class,
            ReceivedViaOptionSeeder::class,
            ReceivedFromOptionSeeder::class,
            CMUOptionSeeder::class,
            OffenceTypeSeeder::class,
            RolesAndPermissionsSeeder::class,
            ReferenceDataSeeder::class,
            NcciaOfficesSeeder::class,
            CircleTenancySeeder::class,
        ]);

        $roles = [
            'admin',
            'operator',
            'verification_officer',
            'circle_incharge',
            'enquiry_officer',
            'investigation_officer',
            'moharrar',
            'reader_branch',
            'ad_legal',
            'dd_legal',
            'additional_director',
            'ad_administration',
            'director_general',
        ];

        foreach ($roles as $role) {
            $user = User::create([
                'name' => ucwords(str_replace('_', ' ', $role)),
                'email' => str_replace('_', '.', $role) . '@nccia.gov.pk',
                'password' => bcrypt('password'),
                'role' => $role,
                'designation' => $this->designationFor($role),
                'circle_id' => \App\Models\Circle::inRandomOrder()->first()->id,
                'zone_id' => \App\Models\Zone::inRandomOrder()->first()->id,
            ]);
            $user->assignRole($role);
        }

        // Ensure admin@admin.com exists for login
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );
        $admin->assignRole('admin');

        $this->call(TestDataSeeder::class);
    }

    private function designationFor(string $role): string
    {
        return match ($role) {
            'operator' => 'Assistant Sub Inspector',
            'verification_officer' => 'Sub Inspector',
            'circle_incharge' => 'Deputy Superintendent of Police',
            'enquiry_officer' => 'Inspector',
            'investigation_officer' => 'Inspector',
            'moharrar' => 'Assistant Sub Inspector',
            'reader_branch' => 'Assistant',
            'ad_legal' => 'Assistant Director Legal',
            'dd_legal' => 'Deputy Director Legal',
            'additional_director' => 'Additional Director',
            'ad_administration' => 'Assistant Director Administration',
            'director_general' => 'Director General',
            default => 'Officer',
        };
    }
}

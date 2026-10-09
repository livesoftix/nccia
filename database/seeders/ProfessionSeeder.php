<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfessionSeeder extends Seeder
{
    public function run(): void
    {
        $professions = [
            'Government Employee',
            'Private Sector Employee',
            'Business Owner',
            'Student',
            'Lawyer / Legal Professional',
            'Journalist / Media',
            'Bank Employee',
            'Teacher / Academician',
            'Retired',
            'Other',
        ];

        foreach ($professions as $name) {
            DB::table('professions')->insertOrIgnore(['name' => $name]);
        }
    }
}

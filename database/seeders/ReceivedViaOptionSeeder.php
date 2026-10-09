<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReceivedViaOptionSeeder extends Seeder
{
    public function run(): void
    {
        $options = [
            'Email',
            'Telephone',
            'Postal Service',
            'Individually (Walk-in)',
            'Mobile App',
            'Online Form / Portal',
            'Tipline (National)',
            'Tipline (International)',
        ];

        foreach ($options as $name) {
            DB::table('received_via_options')->insertOrIgnore(['name' => $name]);
        }
    }
}

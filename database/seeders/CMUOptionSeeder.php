<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CMUOptionSeeder extends Seeder
{
    public function run(): void
    {
        $options = [
            'NCCIA - HQs',
            'Zonal Directorate - Lahore',
            'Zonal Directorate - Karachi',
            'Zonal Directorate - Islamabad',
            'CCRC - LHR',
            'CCRC - KHI',
            'CCRC - ISB',
        ];

        foreach ($options as $name) {
            DB::table('cmu_options')->insertOrIgnore(['name' => $name]);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReceivedFromOptionSeeder extends Seeder
{
    public function run(): void
    {
        $options = [
            ['group' => 'Government Sector', 'name' => 'President Office'],
            ['group' => 'Government Sector', 'name' => 'PM Office'],
            ['group' => 'Government Sector', 'name' => 'Apex Courts'],
            ['group' => 'Government Sector', 'name' => 'Ministry / Department'],
            ['group' => 'Government Sector', 'name' => 'Other Government Department'],
            ['group' => 'Private Sector', 'name' => 'Bank / Financial Institution'],
            ['group' => 'Private Sector', 'name' => 'Organization / Company'],
            ['group' => 'Private Sector', 'name' => 'University / Educational Institute'],
            ['group' => 'Private Sector', 'name' => 'NGO'],
            ['group' => 'Private Sector', 'name' => 'Other Private Office'],
            ['group' => 'Individual', 'name' => 'General Public'],
            ['group' => 'Individual', 'name' => 'Anonymous'],
        ];

        DB::table('received_from_options')->insertOrIgnore($options);
    }
}

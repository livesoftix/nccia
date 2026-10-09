<?php

namespace Database\Seeders;

use App\Services\RoleWorkAssigner;
use Illuminate\Database\Seeder;

/**
 * Ensures each flowchart role has assigned working items.
 * Safe to re-run on live (only fills gaps / unassigned rows).
 */
class AssignRoleWorkSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(RoleWorkAssigner::class)->assignAll(50);

        if ($this->command) {
            foreach ($result as $key => $count) {
                $this->command->info("{$key}: {$count}");
            }
        }
    }
}

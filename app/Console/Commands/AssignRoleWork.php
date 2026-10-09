<?php

namespace App\Console\Commands;

use App\Services\RoleWorkAssigner;
use Illuminate\Console\Command;

class AssignRoleWork extends Command
{
    protected $signature = 'nccia:assign-role-work {--limit=40 : Max items per assignment step}';

    protected $description = 'Assign unassigned complaints/verifications/enquiries/cases to officers by role (flowchart stages)';

    public function handle(RoleWorkAssigner $assigner): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $this->info('Assigning role-based work (limit ' . $limit . ' per step)...');

        $result = $assigner->assignAll($limit);

        foreach ($result as $key => $count) {
            $this->line(sprintf('  %-24s %d', $key . ':', $count));
        }

        $this->info('Done. Officers should now see pending tasks for their stage.');

        return self::SUCCESS;
    }
}

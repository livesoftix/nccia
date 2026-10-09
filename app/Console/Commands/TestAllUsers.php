<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class TestAllUsers extends Command
{
    protected $signature = 'nccia:test-users
        {--role= : Filter users by role}
        {--limit=100 : Maximum number of users to audit}';

    protected $description = 'Audit all registered user accounts, roles, circles, and authentication health in NCCIA';

    public function handle(): int
    {
        $this->newLine();
        $this->info("====================================================================");
        $this->info("            NCCIA USER ACCOUNTS & ROLES AUDIT                       ");
        $this->info("====================================================================");

        $query = User::with(['roles', 'circle:id,name,code', 'zone:id,name,code'])->orderBy('id');

        if ($role = $this->option('role')) {
            $query->role($role);
        }

        $limit = (int) $this->option('limit');
        $users = $query->limit($limit)->get();

        if ($users->isEmpty()) {
            $this->warn("No users found matching criteria.");
            return 0;
        }

        $tableData = [];
        $healthy = 0;
        $warnings = 0;

        foreach ($users as $user) {
            $roles = $user->getRoleNames()->toArray();
            $rolesStr = !empty($roles) ? implode(', ', $roles) : ($user->role ?: '<fg=red>None</>');
            $circleStr = $user->circle ? "{$user->circle->name} ({$user->circle->code})" : ($user->zone ? "Zone: {$user->zone->name}" : 'HQ / Global');
            $status = $user->status ?? 'active';

            $issues = [];
            if (empty($roles) && empty($user->role)) {
                $issues[] = 'No role assigned';
            }
            if ($user->isSuspended()) {
                $issues[] = 'Suspended';
            }

            if (empty($issues)) {
                $healthy++;
                $healthStr = '<info>✔ HEALTHY</info>';
            } else {
                $warnings++;
                $healthStr = '<fg=red>✖ ' . implode(', ', $issues) . '</fg=red>';
            }

            $tableData[] = [
                $user->id,
                $user->name,
                $user->email,
                $rolesStr,
                $circleStr,
                $status === 'active' ? "<info>{$status}</info>" : "<fg=yellow>{$status}</fg=yellow>",
                $healthStr,
            ];
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Role(s)', 'Circle / Scope', 'Status', 'Audit Result'],
            $tableData
        );

        $this->newLine();
        $this->info("--------------------------------------------------------------------");
        $warnText = $warnings > 0 ? "<fg=yellow>Issues Found: {$warnings}</fg=yellow>" : "Issues: 0";
        $this->info("Total Users Audited: " . $users->count() . " | <info>Healthy: {$healthy}</info> | {$warnText}");
        $this->info("--------------------------------------------------------------------");
        $this->newLine();

        return 0;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use App\Models\InvestigationOfficer;
use App\Models\Circle;
use App\Models\Zone;

return new class extends Migration
{
    public function up(): void
    {
        $ios = InvestigationOfficer::whereNotNull('user_id')->get();
        foreach ($ios as $io) {
            $user = User::find($io->user_id);
            if (!$user) continue;

            $update = [];
            if ($io->designation) $update['designation'] = $io->designation;
            if ($io->circle) {
                $circle = Circle::where('name', $io->circle)->orWhere('code', $io->circle)->first();
                if ($circle) $update['circle_id'] = $circle->id;
            }
            if ($io->zone) {
                $zone = Zone::where('name', $io->zone)->orWhere('code', $io->zone)->first();
                if ($zone) $update['zone_id'] = $zone->id;
            }

            if (!empty($update)) {
                $user->update($update);
            }
        }
    }

    public function down(): void
    {
        // No rollback
    }
};

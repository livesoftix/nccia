<?php

use App\Models\Circle;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $circles = Circle::all()->keyBy('code');

        $map = [
            'lhr' => 'LHR',
            'lahore' => 'LHR',
            'grw' => 'GRW',
            'gujranwala' => 'GRW',
            'rwp' => 'RWP',
            'rawalpindi' => 'RWP',
            'mux' => 'MUX',
            'multan' => 'MUX',
            'fsd' => 'FSD',
            'faisalabad' => 'FSD',
            'pew' => 'PEW',
            'peshawar' => 'PEW',
            'khi' => 'KHI',
            'karachi' => 'KHI',
            'uet' => 'UET',
            'quetta' => 'UET',
            'gwd' => 'GWD',
            'gwadar' => 'GWD',
            'glt' => 'GLT',
            'gilgit' => 'GLT',
            'atd' => 'ATD',
            'abbottabad' => 'ATD',
            'dik' => 'DIK',
            'ismail' => 'DIK',
            'skr' => 'SKR',
            'sukkur' => 'SKR',
            'isb' => 'ISB',
            'islamabad' => 'ISB',
        ];

        $users = User::all();
        foreach ($users as $user) {
            $haystack = strtolower(($user->email ?? '') . ' ' . ($user->name ?? '') . ' ' . ($user->designation ?? ''));
            $matchedCode = null;

            foreach ($map as $needle => $code) {
                if (str_contains($haystack, $needle)) {
                    $matchedCode = $code;
                    break;
                }
            }

            $updateData = [];

            if ($matchedCode && isset($circles[$matchedCode])) {
                $circle = $circles[$matchedCode];
                if (!$user->circle_id || (int)$user->circle_id !== (int)$circle->id) {
                    $updateData['circle_id'] = $circle->id;
                }
                if (!$user->zone_id && $circle->zone_id) {
                    $updateData['zone_id'] = $circle->zone_id;
                }
            } elseif (($user->role === 'circle_incharge' || str_contains(strtolower($user->designation ?? ''), 'incharge')) && !$user->circle_id) {
                // Default unassigned circle incharge to Lahore circle
                $lhr = $circles['LHR'] ?? Circle::first();
                if ($lhr) {
                    $updateData['circle_id'] = $lhr->id;
                    if ($lhr->zone_id) {
                        $updateData['zone_id'] = $lhr->zone_id;
                    }
                }
            }

            // Check if user is Punjab Zonal Director
            if (str_contains($haystack, 'punjab') && (str_contains($haystack, 'director') || str_contains($haystack, 'zonal'))) {
                $punjabZone = Zone::where('code', 'PB')->orWhere('name', 'like', '%Punjab%')->first();
                if ($punjabZone) {
                    $updateData['zone_id'] = $punjabZone->id;
                }
            }

            if (!empty($updateData)) {
                DB::table('users')->where('id', $user->id)->update($updateData);
            }
        }
    }

    public function down(): void
    {
    }
};

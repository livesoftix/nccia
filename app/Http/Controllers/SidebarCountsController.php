<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Message;
use App\Models\Verification;
use App\Models\VerificationReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SidebarCountsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'verifications' => 0,
                'reports' => 0,
                'enquiries' => 0,
                'messages' => 0,
            ]);
        }

        $user->loadMissing('roles');
        
        // Track that the user is online right now
        \Illuminate\Support\Facades\Cache::put('user_online_' . $user->id, true, now()->addMinutes(2));

        $key = 'sidebar:v2:' . $user->id;

        $data = Cache::remember($key, 30, function () use ($user) {
            return [
                'verifications' => $this->verificationsCount($user),
                'reports' => $this->reportsCount($user),
                'enquiries' => $this->enquiriesCount($user),
                'messages' => Message::where('receiver_id', $user->id)->where('is_read', false)->count(),
            ];
        });

        if (Cache::has('login_alert_' . $user->id)) {
            $data['security_alert'] = 'Security Alert: Someone just tried to login to your account using correct credentials from IP: ' . Cache::pull('login_alert_' . $user->id);
        }

        return response()->json($data);
    }

    private function verificationsCount($user): int
    {
        $q = Verification::visibleTo($user);

        // VO: only their open work
        if ($user->hasRole('verification_officer') && !$user->hasAnyRole(['admin', 'circle_incharge', 'director_general'])) {
            return (clone $q)
                ->where('verification_officer_id', $user->id)
                ->whereIn('status', ['assigned', 'in_progress', 'sent_back'])
                ->count();
        }

        // CI / Admin / DG: queue needing VO assignment (not the same as report approvals)
        if ($user->hasAnyRole(['circle_incharge', 'admin', 'director_general'])) {
            return (clone $q)->where('status', 'pending_assignment')->count();
        }

        return (clone $q)
            ->whereIn('status', ['pending_assignment', 'assigned', 'in_progress', 'sent_back'])
            ->count();
    }

    private function reportsCount($user): int
    {
        // CI / supervisors: submitted verifications awaiting report approval
        if ($user->hasAnyRole(['circle_incharge', 'admin', 'director_general'])) {
            return Verification::visibleTo($user)->where('status', 'submitted')->count();
        }

        // VO: open assignments that still have no verification report
        if ($user->hasRole('verification_officer')) {
            return Verification::visibleTo($user)
                ->where('verification_officer_id', $user->id)
                ->whereIn('status', ['assigned', 'in_progress', 'sent_back'])
                ->whereDoesntHave('complaint.latestVerificationReport')
                ->count();
        }

        return VerificationReport::visibleTo($user)->count();
    }

    private function enquiriesCount($user): int
    {
        $q = Enquiry::visibleTo($user);

        if ($user->hasRole('enquiry_officer') && !$user->hasAnyRole(['admin', 'circle_incharge', 'director_general'])) {
            return (clone $q)
                ->where('enquiry_officer_id', $user->id)
                ->whereIn('status', ['assigned', 'in_progress', 'registered', 'pending', 'working'])
                ->count();
        }

        if ($user->hasAnyRole(['circle_incharge', 'admin', 'director_general'])) {
            return (clone $q)
                ->whereIn('status', ['registered', 'cfr_submitted'])
                ->count();
        }

        if ($user->hasRole('reader_branch')) {
            return (clone $q)
                ->where(function ($qq) {
                    $qq->whereNull('enquiry_number')->orWhere('enquiry_number', '');
                })
                ->count();
        }

        return (clone $q)
            ->whereIn('status', ['registered', 'assigned', 'in_progress', 'cfr_submitted'])
            ->count();
    }
}

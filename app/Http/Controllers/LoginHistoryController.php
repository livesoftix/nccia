<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use Illuminate\Http\Request;

class LoginHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = LoginHistory::with('user:id,name,email,role,designation,circle_id,zone_id')
            ->latest('logged_in_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('ip')) {
            $ip = trim($request->ip);
            $query->where(function ($q) use ($ip) {
                $q->where('ip_address', 'like', "%{$ip}%")
                  ->orWhere('real_ip', 'like', "%{$ip}%");
            });
        }

        if ($request->filled('method')) {
            $query->where('login_method', $request->method);
        }

        if ($request->filled('spoofed')) {
            $query->where('is_spoofed', $request->boolean('spoofed'));
        }

        if ($request->filled('from')) {
            $query->where('logged_in_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('logged_in_at', '<=', $request->to);
        }

        return $query->paginate(15);
    }

    public function userHistory(Request $request, int $user)
    {
        return LoginHistory::where('user_id', $user)
            ->latest('logged_in_at')
            ->paginate(15);
    }

    public function stats(Request $request)
    {
        $query = LoginHistory::query();

        if ($request->filled('from')) {
            $query->where('logged_in_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('logged_in_at', '<=', $request->to);
        }

        $total = $query->count();
        $spoofed = (clone $query)->where('is_spoofed', true)->count();
        $uniqueUsers = (clone $query)->distinct('user_id')->count('user_id');
        $uniqueIps = (clone $query)->distinct('ip_address')->count('ip_address');

        $byMethod = (clone $query)
            ->selectRaw('login_method, count(*) as count')
            ->groupBy('login_method')
            ->pluck('count', 'login_method');

        $recentSpoofed = (clone $query)
            ->where('is_spoofed', true)
            ->latest('logged_in_at')
            ->limit(10)
            ->with('user:id,name,email')
            ->get();

        return response()->json([
            'total_logins' => $total,
            'spoofed_count' => $spoofed,
            'unique_users' => $uniqueUsers,
            'unique_ips' => $uniqueIps,
            'by_method' => $byMethod,
            'recent_spoofed' => $recentSpoofed,
        ]);
    }
}
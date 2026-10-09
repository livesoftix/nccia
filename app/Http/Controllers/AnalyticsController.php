<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\Verification;
use App\Services\DashboardStatsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __construct(private DashboardStatsService $statsService)
    {
    }

    public function index()
    {
        $user = request()->user();
        $year = (int) request('year', now()->year);

        $cacheKey = 'analytics:v2:' . ($user?->id ?: 0) . ':' . $year;

        $payload = Cache::remember($cacheKey, 300, function () use ($user, $year) {
            $queries = $this->statsService->scopedQueries($user);
            $cmpQuery = $queries['cmpQuery'];
            $enqQuery = $queries['enqQuery'];

            $verQuery = Verification::query()
                ->whereIn('complaint_id', (clone $cmpQuery)->select('id'));
            $caseQuery = CaseFile::query()
                ->whereIn('enquiry_id', (clone $enqQuery)->select('id'));

            $cmpStats = $this->statsService->complaintStats($cmpQuery);
            $caseStats = $this->statsService->caseStats($caseQuery, $year);
            $workflowStages = $this->statsService->workflowStages($cmpQuery);

            $totalComplaints = $cmpStats['total_complaints'];
            $resolvedComplaints = $cmpStats['resolved_complaints'];

            $avgResolutionDays = (clone $cmpQuery)->where(function ($q) {
                $q->whereNotNull('final_status')
                    ->orWhereIn('status', ['invalid', 'irrelevant']);
            })
                ->selectRaw('COALESCE(AVG(DATEDIFF(COALESCE(updated_at, created_at), created_at)), 0) as avg')
                ->value('avg');

            $totalCases = (clone $caseQuery)->count();
            $convictionRate = $totalCases > 0
                ? (int) round(($caseStats['finalized_cases'] / $totalCases) * 100)
                : 0;

            $officerWorkload = $this->officerWorkload($user);
            $totalAssignments = $officerWorkload->sum('assigned');
            $completedAssignments = $officerWorkload->sum('completed');

            $performanceMetrics = [
                'totalComplaints' => $totalComplaints,
                'resolvedComplaints' => $resolvedComplaints,
                'pendingComplaints' => $totalComplaints - $resolvedComplaints,
                'avgResolutionDays' => round((float) $avgResolutionDays, 1),
                'convictionRate' => $convictionRate,
                'officerEfficiency' => $totalAssignments > 0
                    ? (int) round(($completedAssignments / $totalAssignments) * 100)
                    : 0,
            ];

            $monthlyTrends = $this->statsService->monthlyTrends($cmpQuery, $enqQuery, $year);
            $categoryBreakdown = $this->statsService->categoryBreakdown($cmpQuery);

            $circlePerformance = DB::table('circles')
                ->leftJoin('complaints', 'complaints.circle_id', '=', 'circles.id')
                ->whereIn('complaints.id', (clone $cmpQuery)->select('id'))
                ->selectRaw('circles.id, circles.name as circle, count(complaints.id) as total, sum(case when complaints.status = ? then 1 else 0 end) as resolved', ['complete'])
                ->groupBy('circles.id', 'circles.name')
                ->get()
                ->map(function ($c) {
                    $c->percentage = $c->total > 0 ? (int) round(($c->resolved / $c->total) * 100) : 0;

                    return $c;
                });

            $topOfficers = $officerWorkload->where('assigned', '>', 0)
                ->sortByDesc('completionRate')
                ->take(10)
                ->map(fn ($o) => [
                    'id' => $o['id'],
                    'name' => $o['name'],
                    'role' => $o['role'],
                    'casesHandled' => $o['assigned'],
                    'resolutionRate' => $o['completionRate'],
                ])
                ->values();

            $caseOutcomes = $this->statsService->caseOutcomesFromStages($workflowStages);

            return compact(
                'performanceMetrics',
                'monthlyTrends',
                'categoryBreakdown',
                'circlePerformance',
                'officerWorkload',
                'topOfficers',
                'caseOutcomes'
            );
        });

        return response()->json($payload);
    }

    private function officerWorkload($user)
    {
        $officerRoles = [
            'verification_officer' => ['table' => Verification::class, 'col' => 'verification_officer_id', 'done' => ['submitted', 'approved']],
            'enquiry_officer' => ['table' => Enquiry::class, 'col' => 'enquiry_officer_id', 'done' => ['approved', 'closed', 'converted_to_case']],
            'investigation_officer' => ['table' => CaseFile::class, 'col' => 'investigation_officer_id', 'done' => ['closed']],
        ];

        $officers = User::whereHas('roles')
            ->with('roles:id,name')
            ->select('id', 'name', 'role')
            ->get();

        $workload = collect();

        foreach ($officers as $off) {
            $roleName = $off->roles->first()?->name ?? $off->role ?? '';
            $spec = $officerRoles[$roleName] ?? null;
            if (!$spec) {
                continue;
            }

            $model = $spec['table'];
            $base = $model::where($spec['col'], $off->id);

            if (method_exists($model, 'visibleTo') && !$user->seesAllData()) {
                $base = $model::visibleTo($user)->where($spec['col'], $off->id);
            }

            $assigned = (clone $base)->count();
            $completed = (clone $base)->whereIn('status', $spec['done'])->count();

            $workload->push([
                'id' => $off->id,
                'name' => $off->name,
                'role' => ucwords(str_replace('_', ' ', $roleName)),
                'assigned' => $assigned,
                'completed' => $completed,
                'pending' => $assigned - $completed,
                'completionRate' => $assigned > 0 ? (int) round(($completed / $assigned) * 100) : 0,
            ]);
        }

        return $workload->sortByDesc('assigned')->values();
    }
}

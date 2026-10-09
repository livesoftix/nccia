<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\OffenceType;
use App\Models\Verification;
use App\Services\DashboardStatsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private DashboardStatsService $statsService)
    {
    }

    public function index()
    {
        $year = (int) request('year', now()->year);

        $cached = Cache::remember('dash:web:v1:' . $year, 180, function () use ($year) {
            $queries = $this->statsService->scopedQueries(null);
            $verStats = $this->statsService->verificationStats($queries['verQuery']);
            $enqStats = $this->statsService->enquiryStats($queries['enqQuery']);
            $caseStats = $this->statsService->caseStats($queries['caseQuery'], $year);
            $cmpStats = $this->statsService->complaintStats($queries['cmpQuery']);

            return [
                'stats' => [
                    'total_verifications' => $verStats['total'],
                    'pending_verifications' => $verStats['pending'],
                    'pending_review' => $verStats['pending_review'],
                    'avg_wait_days' => $verStats['avg_wait_days'],
                    'finalized_cases' => $caseStats['finalized_cases'],
                    'finalized_this_month' => $caseStats['finalized_this_month'],
                    'converted_to_enquiry' => $enqStats['converted_to_enquiry'],
                    'active_enquiries' => $enqStats['active_enquiries'],
                    'recommended_closure' => $enqStats['recommended_closure'],
                    'awaiting_approval' => $enqStats['awaiting_approval'],
                    'closed_non_pursuance' => $enqStats['closed_non_pursuance'],
                    'closed_np_last_30' => $enqStats['closed_np_last_30'],
                    'closed_non_evidence' => $enqStats['closed_non_evidence'],
                    'ne_under_review' => $enqStats['ne_under_review'],
                    'avg_processing_days' => $cmpStats['avg_processing_days'],
                    'transfer_circles' => $cmpStats['transfer_circles'],
                    'merge_other' => $this->statsService->mergeOtherCount($queries['cmpQuery']),
                    'jurisdiction_wanted' => $enqStats['jurisdiction_wanted'],
                    'overall_performance' => $cmpStats['overall_performance'],
                ],
                'monthlyTrends' => $this->statsService->monthlyTrends($queries['cmpQuery'], $queries['enqQuery'], $year),
                'categoryBreakdown' => $this->formatCategoryBreakdown(
                    $this->statsService->categoryBreakdown($queries['cmpQuery'])
                ),
            ];
        });

        $stats = $cached['stats'];
        $monthlyTrends = $cached['monthlyTrends'];
        $categoryBreakdown = $cached['categoryBreakdown'];

        $recentComplaints = Complaint::with('circle')->latest()->take(5)->get();

        $recentActivity = collect();
        if (class_exists(\Spatie\Activitylog\Models\Activity::class)) {
            $recentActivity = \Spatie\Activitylog\Models\Activity::latest()->take(10)->get()
                ->map(fn ($a) => (object) [
                    'description' => $a->description,
                    'causer_name' => $a->causer?->name ?? 'System',
                    'created_at' => $a->created_at,
                ]);
        } else {
            $recentFromComplaints = Complaint::latest()->take(3)->get()->map(fn ($c) => (object) [
                'description' => "New complaint registered — {$c->tracking_no}",
                'causer_name' => $c->operator_name ?? 'N/A',
                'created_at' => $c->created_at,
            ]);
            $recentFromVerifications = Verification::with('complaint')->latest()->take(3)->get()->map(fn ($v) => (object) [
                'description' => 'Verification ' . ($v->status ?? 'updated') . ' — #' . ($v->complaint?->tracking_no),
                'causer_name' => $v->officer?->name ?? 'N/A',
                'created_at' => $v->created_at,
            ]);
            $recentFromEnquiries = Enquiry::latest()->take(2)->get()->map(fn ($e) => (object) [
                'description' => "Enquiry {$e->enquiry_number} — {$e->status}",
                'causer_name' => $e->officer?->name ?? 'N/A',
                'created_at' => $e->created_at,
            ]);
            $recentFromCases = CaseFile::latest()->take(2)->get()->map(fn ($cf) => (object) [
                'description' => "Case {$cf->fir_no} — {$cf->status}",
                'causer_name' => $cf->investigationOfficer?->name ?? 'N/A',
                'created_at' => $cf->created_at,
            ]);

            $recentActivity = $recentFromComplaints
                ->concat($recentFromVerifications)
                ->concat($recentFromEnquiries)
                ->concat($recentFromCases)
                ->sortByDesc('created_at')
                ->take(10)
                ->values();
        }

        $accountLevels = [];
        $oldestYear = Complaint::min(DB::raw('YEAR(created_at)')) ?? now()->year;
        $availableYears = range(now()->year, $oldestYear);

        return view('pages.dashboard', compact(
            'stats',
            'monthlyTrends',
            'categoryBreakdown',
            'recentComplaints',
            'recentActivity',
            'accountLevels',
            'availableYears',
        ));
    }

    public function apiIndex()
    {
        try {
            $user = request()->user();
            $role = $user?->roles?->first()?->name ?? $user?->role ?? 'operator';
            $year = (int) request('year', now()->year);
            $dateFrom = request('date_from');
            $dateTo = request('date_to');
            $accountFilter = request('account');

            $cacheKey = 'dash:v5:' . ($user?->id ?: 0) . ':' . ($user?->circle_id ?: 'none') . ':' . md5(json_encode([$role, $year, $dateFrom, $dateTo, $accountFilter]));

            $payload = Cache::remember($cacheKey, 180, function () use ($user, $role, $year, $dateFrom, $dateTo) {
                $queries = $this->statsService->scopedQueries($user, $dateFrom, $dateTo);
                $verStats = $this->statsService->verificationStats($queries['verQuery']);
                $enqStats = $this->statsService->enquiryStats($queries['enqQuery']);
                $caseStats = $this->statsService->caseStats($queries['caseQuery'], $year);
                $cmpStats = $this->statsService->complaintStats($queries['cmpQuery']);
                $workflowStages = $this->statsService->workflowStages($queries['cmpQuery']);
                $roleMetrics = $this->statsService->roleMetrics($user, $queries['enqQuery'], $queries['caseQuery'], $queries['verQuery']);

                $stats = [
                    'total_complaints' => $cmpStats['total_complaints'],
                    'complete_registrations' => $cmpStats['complete_registrations'],
                    'incomplete_registrations' => $cmpStats['incomplete_registrations'],
                    'with_verification' => $verStats['total'],
                    'total_verifications' => $verStats['total'],
                    'pending_verifications' => $verStats['pending'],
                    'pending_review' => $verStats['pending_review'],
                    'avg_wait_days' => $verStats['avg_wait_days'],
                    'finalized_cases' => $caseStats['finalized_cases'],
                    'finalized_this_month' => $caseStats['finalized_this_month'],
                    'converted_to_enquiry' => $enqStats['converted_to_enquiry'],
                    'active_enquiries' => $enqStats['active_enquiries'],
                    'recommended_closure' => $enqStats['recommended_closure'],
                    'awaiting_approval' => $enqStats['awaiting_approval'],
                    'closed_non_pursuance' => $enqStats['closed_non_pursuance'],
                    'closed_np_last_30' => $enqStats['closed_np_last_30'],
                    'closed_non_evidence' => $enqStats['closed_non_evidence'],
                    'ne_under_review' => $enqStats['ne_under_review'],
                    'avg_processing_days' => $cmpStats['avg_processing_days'],
                    'transfer_circles' => $cmpStats['transfer_circles'],
                    'merge_other' => $this->statsService->mergeOtherCount($queries['cmpQuery']),
                    'jurisdiction_wanted' => $enqStats['jurisdiction_wanted'],
                    'overall_performance' => $cmpStats['overall_performance'],
                    'avg_completion' => $this->statsService->avgCompletion($workflowStages),
                    'workflow_stages' => $workflowStages,
                    'assigned_enquiries' => $enqStats['assigned_enquiries'],
                    'pending_cfrs' => $enqStats['pending_cfrs'],
                    'unserved_summons' => $roleMetrics['unservedSummons'],
                    'active_investigations' => $caseStats['active_investigations'],
                    'court_hearings_upcoming' => $roleMetrics['courtHearingsUpcoming'],
                    'pending_approvals' => $roleMetrics['pendingApprovals'],
                ];

                $recentLimit = ($role === 'operator') ? 20 : 6;
                $recentComplaints = (clone $queries['cmpQuery'])
                    ->with(['verification.officer', 'enquiry', 'caseFiles'])
                    ->latest('id')
                    ->take($recentLimit)
                    ->get()
                    ->map(function (Complaint $c) {
                        return [
                            'id' => $c->id,
                            'tracking_no' => $c->tracking_no,
                            'diary_no' => $c->diary_no,
                            'complainant_name' => $c->complainant_name,
                            'offence_type' => $c->offence_type,
                            'priority_type' => $c->priority_type,
                            'operator_name' => $c->operator_name,
                            'status' => $c->status,
                            'scrutiny_result' => $c->scrutiny_result,
                            'created_at' => $c->created_at?->toISOString(),
                            'progress_percent' => $c->progressPercent(),
                            'progress_stage' => $c->progressStage(),
                            'verification_status' => $c->verification?->status,
                            'officer_name' => $c->verification?->officer?->name,
                        ];
                    });

                $recentFromComplaints = (clone $queries['cmpQuery'])->latest('id')->take(4)->get()->map(fn ($c) => [
                    'type' => 'complaint',
                    'id' => $c->id,
                    'description' => 'Complaint registered — #' . ($c->tracking_no ?: $c->diary_no ?: $c->id),
                    'causer_name' => $c->operator_name ?: 'Front Desk Operator',
                    'created_at' => $c->created_at?->toISOString(),
                ]);
                $recentFromVerifications = (clone $queries['verQuery'])->with('complaint', 'officer')->latest('id')->take(4)->get()->map(fn ($v) => [
                    'type' => 'verification',
                    'id' => $v->id,
                    'description' => 'Verification (' . ($v->status ?? 'updated') . ') — #' . ($v->complaint?->tracking_no ?: $v->id),
                    'causer_name' => $v->officer?->name ?: 'Verification Officer',
                    'created_at' => $v->created_at?->toISOString(),
                ]);
                $recentFromEnquiries = (clone $queries['enqQuery'])->with('officer')->latest('id')->take(4)->get()->map(fn ($e) => [
                    'type' => 'enquiry',
                    'id' => $e->id,
                    'description' => "Enquiry {$e->enquiry_number} status: {$e->status}",
                    'causer_name' => $e->officer?->name ?: 'Enquiry Officer',
                    'created_at' => $e->created_at?->toISOString(),
                ]);
                $recentFromCases = (clone $queries['caseQuery'])->with('investigationOfficer')->latest('id')->take(4)->get()->map(fn ($cf) => [
                    'type' => 'case',
                    'id' => $cf->id,
                    'description' => "Case {$cf->fir_no} status: {$cf->status}",
                    'causer_name' => $cf->investigationOfficer?->name ?: 'Investigation Officer',
                    'created_at' => $cf->created_at?->toISOString(),
                ]);

                $recentActivity = $recentFromComplaints
                    ->concat($recentFromVerifications)
                    ->concat($recentFromEnquiries)
                    ->concat($recentFromCases)
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->values();

                return [
                    'stats' => $stats,
                    'monthlyTrends' => $this->statsService->monthlyTrends($queries['cmpQuery'], $queries['enqQuery'], $year),
                    'categoryBreakdown' => $this->statsService->categoryBreakdown($queries['cmpQuery']),
                    'recentComplaints' => $recentComplaints,
                    'recentActivity' => $recentActivity,
                ];
            });

            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    private function formatCategoryBreakdown($categoryBreakdown): \Illuminate\Support\Collection
    {
        return $categoryBreakdown->map(fn ($item) => [
            'name' => $item->name,
            'count' => $item->total,
            'percentage' => $item->percentage,
            'color' => $item->color,
        ]);
    }
}

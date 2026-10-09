<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\Verification;
use Illuminate\Support\Facades\DB;

class DashboardThreeController extends Controller
{
    public function index()
    {
        $year = request('year', now()->year);

        $totalVerifications       = Verification::count();
        $pendingVerifications     = Verification::where('status', 'assigned')->count();
        $pendingReview            = Verification::whereIn('status', ['submitted', 'sent_back'])->count();

        $avgWaitDays = Verification::whereNotNull('assigned_at')
            ->selectRaw('COALESCE(AVG(DATEDIFF(COALESCE(assigned_at, created_at), created_at)), 0) as avg')
            ->value('avg');

        $finalizedCases       = CaseFile::where('status', 'closed')->count();
        $finalizedThisMonth   = CaseFile::where('status', 'closed')
            ->whereYear('updated_at', now()->year)
            ->whereMonth('updated_at', now()->month)
            ->count();

        $convertedToEnquiry = Enquiry::count();
        $activeEnquiries    = Enquiry::whereNotIn('status', ['approved', 'closed'])->count();

        $recommendedClosure = Enquiry::where('recommendation', 'closure')->count();
        $awaitingApproval   = Enquiry::where('status', 'cfr_submitted')->count();

        $closedNonPursuance = Enquiry::where('closure_reason', 'non_pursuance')->count();
        $closedNpLast30     = Enquiry::where('closure_reason', 'non_pursuance')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $closedNonEvidence = Enquiry::where('closure_reason', 'non_evidence')->count();
        $neUnderReview     = Enquiry::where('closure_reason', 'non_evidence')
            ->where('status', 'in_progress')
            ->count();

        $avgProcessingDays = Complaint::whereIn('status', ['complete', 'invalid', 'irrelevant'])
            ->selectRaw('COALESCE(AVG(DATEDIFF(updated_at, created_at)), 0) as avg')
            ->value('avg');

        $transferCircles = Complaint::whereNotNull('circle_id')
            ->where('status', 'complete')
            ->count();

        $mergeOther    = Complaint::has('enquiries', '>=', 1)->count();
        $jurisdictionWanted = Enquiry::where('status', 'registered')->count();

        $totalComplaints = Complaint::count();
        $resolvedComplaints = Complaint::where('status', 'complete')->count();
        $overallPerformance = $totalComplaints > 0
            ? round(($resolvedComplaints / $totalComplaints) * 100)
            : 0;

        $stats = [
            'total_verifications'   => $totalVerifications,
            'pending_verifications' => $pendingVerifications,
            'pending_review'        => $pendingReview,
            'avg_wait_days'         => round((float) $avgWaitDays, 1),
            'finalized_cases'       => $finalizedCases,
            'finalized_this_month'  => $finalizedThisMonth,
            'converted_to_enquiry'  => $convertedToEnquiry,
            'active_enquiries'      => $activeEnquiries,
            'recommended_closure'   => $recommendedClosure,
            'awaiting_approval'     => $awaitingApproval,
            'closed_non_pursuance'  => $closedNonPursuance,
            'closed_np_last_30'     => $closedNpLast30,
            'closed_non_evidence'   => $closedNonEvidence,
            'ne_under_review'       => $neUnderReview,
            'avg_processing_days'   => round((float) $avgProcessingDays, 1),
            'transfer_circles'      => $transferCircles,
            'merge_other'           => $mergeOther,
            'jurisdiction_wanted'   => $jurisdictionWanted,
            'overall_performance'   => $overallPerformance,
        ];

        $monthlyTrends = [];
        for ($m = 1; $m <= 12; $m++) {
            $received = Complaint::whereYear('created_at', $year)
                ->whereMonth('created_at', $m)
                ->count();

            $resolved = Complaint::where('status', 'complete')
                ->whereYear('updated_at', $year)
                ->whereMonth('updated_at', $m)
                ->count();

            $converted = Enquiry::whereYear('created_at', $year)
                ->whereMonth('created_at', $m)
                ->count();

            $monthlyTrends[] = [
                'month'     => date('M', mktime(0, 0, 0, $m, 1)),
                'received'  => $received,
                'resolved'  => $resolved,
                'converted' => $converted,
            ];
        }

        $rawCategories = Complaint::select('offence_type', DB::raw('count(*) as total'))
            ->groupBy('offence_type')
            ->orderByDesc('total')
            ->get();

        $totalCategorized = $rawCategories->sum('total');
        $categoryBreakdown = $rawCategories->map(function ($item) use ($totalCategorized) {
            return [
                'name'       => $item->offence_type,
                'count'      => $item->total,
                'percentage' => $totalCategorized > 0
                    ? round(($item->total / $totalCategorized) * 100)
                    : 0,
            ];
        });

        $recentComplaints = Complaint::with('circle')
            ->latest()
            ->take(5)
            ->get();

        $recentActivity = collect();

        if (class_exists(\Spatie\Activitylog\Models\Activity::class)) {
            $recentActivity = \Spatie\Activitylog\Models\Activity::latest()->take(10)->get()
                ->map(function ($a) {
                    return (object) [
                        'description' => $a->description,
                        'causer_name' => $a->causer?->name ?? 'System',
                        'created_at'  => $a->created_at,
                    ];
                });
        } else {
            $recentFromComplaints = Complaint::latest()->take(3)->get()->map(function ($c) {
                return (object) [
                    'description' => "New complaint registered — {$c->tracking_no}",
                    'causer_name' => $c->operator_name ?? 'N/A',
                    'created_at'  => $c->created_at,
                ];
            });

            $recentFromVerifications = Verification::with('complaint')
                ->latest()->take(3)->get()->map(function ($v) {
                    return (object) [
                        'description' => "Verification " . ($v->status ?? 'updated') . " — #{$v->complaint?->tracking_no}",
                        'causer_name' => $v->officer?->name ?? 'N/A',
                        'created_at'  => $v->created_at,
                    ];
                });

            $recentFromEnquiries = Enquiry::latest()->take(2)->get()->map(function ($e) {
                return (object) [
                    'description' => "Enquiry {$e->enquiry_number} — {$e->status}",
                    'causer_name' => $e->officer?->name ?? 'N/A',
                    'created_at'  => $e->created_at,
                ];
            });

            $recentFromCases = CaseFile::latest()->take(2)->get()->map(function ($cf) {
                return (object) [
                    'description' => "Case {$cf->fir_no} — {$cf->status}",
                    'causer_name' => $cf->investigationOfficer?->name ?? 'N/A',
                    'created_at'  => $cf->created_at,
                ];
            });

            $recentActivity = $recentFromComplaints
                ->concat($recentFromVerifications)
                ->concat($recentFromEnquiries)
                ->concat($recentFromCases)
                ->sortByDesc('created_at')
                ->take(10)
                ->values();
        }

        $accountLevels = [];

        $oldestYear = Complaint::min(DB::raw("YEAR(created_at)")) ?? now()->year;
        $availableYears = range(now()->year, $oldestYear);

        return view('pages.dashboard3', compact(
            'stats',
            'monthlyTrends',
            'categoryBreakdown',
            'recentComplaints',
            'recentActivity',
            'accountLevels',
            'availableYears',
        ));
    }
}

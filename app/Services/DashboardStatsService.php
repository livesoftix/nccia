<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\CourtCase;
use App\Models\Enquiry;
use App\Models\OffenceType;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    /**
     * Returns true when the current database connection is SQLite.
     * Used throughout to emit driver-appropriate raw SQL expressions.
     */
    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /**
     * Returns a SQL expression that computes the number of days between two
     * datetime columns, compatible with both MySQL and SQLite.
     *
     * MySQL  : DATEDIFF(end_col, start_col)
     * SQLite : ROUND(julianday(end_col) - julianday(start_col))
     */
    private function dateDiffExpr(string $endCol, string $startCol): string
    {
        return $this->isSqlite()
            ? "ROUND(julianday({$endCol}) - julianday({$startCol}))"
            : "DATEDIFF({$endCol}, {$startCol})";
    }

    /**
     * Returns a SQL expression to extract the month (1–12) from a datetime column.
     * MySQL  : MONTH(col)
     * SQLite : CAST(strftime('%m', col) AS INTEGER)
     */
    private function monthExpr(string $col): string
    {
        return $this->isSqlite()
            ? "CAST(strftime('%m', {$col}) AS INTEGER)"
            : "MONTH({$col})";
    }

    /**
     * Returns a SQL expression to extract the 4-digit year from a datetime column.
     * MySQL  : YEAR(col)
     * SQLite : CAST(strftime('%Y', col) AS INTEGER)
     */
    private function yearExpr(string $col): string
    {
        return $this->isSqlite()
            ? "CAST(strftime('%Y', {$col}) AS INTEGER)"
            : "YEAR({$col})";
    }

    /**
     * @return array{cmp: Builder, ver: Builder, enq: Builder, case: Builder}
     */
    public function scopedQueries(?User $user, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $cmpQuery = $user ? Complaint::visibleTo($user) : Complaint::query();
        $verQuery = $user ? Verification::visibleTo($user) : Verification::query();
        $enqQuery = $user ? Enquiry::visibleTo($user) : Enquiry::query();
        $caseQuery = $user ? CaseFile::visibleTo($user) : CaseFile::query();

        foreach (['cmp' => &$cmpQuery, 'ver' => &$verQuery, 'enq' => &$enqQuery, 'case' => &$caseQuery] as $ref) {
            if ($dateFrom) {
                $ref->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $ref->whereDate('created_at', '<=', $dateTo);
            }
        }

        return compact('cmpQuery', 'verQuery', 'enqQuery', 'caseQuery');
    }

    public function verificationStats(Builder $verQuery): array
    {
        $ddExpr = $this->dateDiffExpr('COALESCE(assigned_at, created_at)', 'created_at');

        $row = (clone $verQuery)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status IN ('submitted', 'sent_back') THEN 1 ELSE 0 END) as pending_review")
            ->selectRaw("COALESCE(AVG(CASE WHEN assigned_at IS NOT NULL THEN {$ddExpr} END), 0) as avg_wait")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'pending_review' => (int) ($row->pending_review ?? 0),
            'avg_wait_days' => round((float) ($row->avg_wait ?? 0), 1),
        ];
    }

    public function enquiryStats(Builder $enqQuery): array
    {
        $since30 = now()->subDays(30)->toDateTimeString();

        $row = (clone $enqQuery)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status NOT IN ('approved', 'closed') THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN recommendation = 'closure' THEN 1 ELSE 0 END) as recommended_closure")
            ->selectRaw("SUM(CASE WHEN status = 'cfr_submitted' THEN 1 ELSE 0 END) as awaiting_approval")
            ->selectRaw("SUM(CASE WHEN closure_reason = 'non_pursuance' THEN 1 ELSE 0 END) as closed_non_pursuance")
            ->selectRaw("SUM(CASE WHEN closure_reason = 'non_pursuance' AND created_at >= '{$since30}' THEN 1 ELSE 0 END) as closed_np_last_30")
            ->selectRaw("SUM(CASE WHEN closure_reason = 'non_evidence' THEN 1 ELSE 0 END) as closed_non_evidence")
            ->selectRaw("SUM(CASE WHEN closure_reason = 'non_evidence' AND status = 'in_progress' THEN 1 ELSE 0 END) as ne_under_review")
            ->selectRaw("SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as jurisdiction_wanted")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('approved', 'closed') THEN 1 ELSE 0 END) as assigned_enquiries")
            ->selectRaw("SUM(CASE WHEN status IN ('in_progress', 'assigned') THEN 1 ELSE 0 END) as pending_cfrs")
            ->selectRaw("SUM(CASE WHEN status = 'cfr_submitted' THEN 1 ELSE 0 END) as cfr_submitted")
            ->first();

        return [
            'converted_to_enquiry' => (int) ($row->total ?? 0),
            'active_enquiries' => (int) ($row->active ?? 0),
            'recommended_closure' => (int) ($row->recommended_closure ?? 0),
            'awaiting_approval' => (int) ($row->awaiting_approval ?? 0),
            'closed_non_pursuance' => (int) ($row->closed_non_pursuance ?? 0),
            'closed_np_last_30' => (int) ($row->closed_np_last_30 ?? 0),
            'closed_non_evidence' => (int) ($row->closed_non_evidence ?? 0),
            'ne_under_review' => (int) ($row->ne_under_review ?? 0),
            'jurisdiction_wanted' => (int) ($row->jurisdiction_wanted ?? 0),
            'assigned_enquiries' => (int) ($row->assigned_enquiries ?? 0),
            'pending_cfrs' => (int) ($row->pending_cfrs ?? 0),
            'cfr_submitted' => (int) ($row->cfr_submitted ?? 0),
        ];
    }

    public function caseStats(Builder $caseQuery, int $year): array
    {
        $row = (clone $caseQuery)->toBase()
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as finalized")
            ->selectRaw("SUM(CASE WHEN status = 'closed' AND YEAR(updated_at) = ? AND MONTH(updated_at) = ? THEN 1 ELSE 0 END) as finalized_this_month", [$year, now()->month])
            ->selectRaw("SUM(CASE WHEN status NOT IN ('approved', 'closed') THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status = 'cfr_submitted' THEN 1 ELSE 0 END) as cfr_submitted")
            ->first();

        return [
            'finalized_cases' => (int) ($row->finalized ?? 0),
            'finalized_this_month' => (int) ($row->finalized_this_month ?? 0),
            'active_investigations' => (int) ($row->active ?? 0),
            'cfr_submitted' => (int) ($row->cfr_submitted ?? 0),
        ];
    }

    public function complaintStats(Builder $cmpQuery): array
    {
        $row = (clone $cmpQuery)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN final_status IS NOT NULL OR status IN ('invalid', 'irrelevant') THEN 1 ELSE 0 END) as resolved")
            ->selectRaw("SUM(CASE WHEN (final_status IS NOT NULL OR status IN ('complete', 'invalid', 'irrelevant')) THEN 1 ELSE 0 END) as processed")
            ->selectRaw('COALESCE(AVG(CASE WHEN final_status IS NOT NULL OR status IN (\'complete\', \'invalid\', \'irrelevant\') THEN DATEDIFF(updated_at, created_at) END), 0) as avg_processing')
            ->selectRaw("SUM(CASE WHEN circle_id IS NOT NULL AND status = 'complete' THEN 1 ELSE 0 END) as transfer_circles")
            ->selectRaw("SUM(CASE WHEN status = 'complete' AND tracking_no IS NOT NULL THEN 1 ELSE 0 END) as complete_count")
            ->selectRaw("SUM(CASE WHEN status = 'incomplete' OR tracking_no IS NULL OR scrutiny_result IN ('incomplete', 'invalid', 'irrelevant') THEN 1 ELSE 0 END) as incomplete_count")
            ->first();

        $total = (int) ($row->total ?? 0);
        $resolved = (int) ($row->resolved ?? 0);

        return [
            'total_complaints' => $total,
            'resolved_complaints' => $resolved,
            'overall_performance' => $total > 0 ? (int) round(($resolved / $total) * 100) : 0,
            'avg_processing_days' => round((float) ($row->avg_processing ?? 0), 1),
            'transfer_circles' => (int) ($row->transfer_circles ?? 0),
            'complete_registrations' => (int) ($row->complete_count ?? 0),
            'incomplete_registrations' => (int) ($row->incomplete_count ?? 0),
        ];
    }

    public function mergeOtherCount(Builder $cmpQuery): int
    {
        return (clone $cmpQuery)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('enquiries')
                    ->whereColumn('enquiries.complaint_id', 'complaints.id');
            })
            ->count();
    }

    public function workflowStages(Builder $cmpQuery): array
    {
        return [
            'resolved' => (clone $cmpQuery)->where(function ($q) {
                $q->whereNotNull('final_status')->orWhereIn('status', ['invalid', 'irrelevant']);
            })->count(),
            'case_filed' => (clone $cmpQuery)->whereNull('final_status')
                ->whereNotIn('status', ['invalid', 'irrelevant'])
                ->whereHas('caseFiles')->count(),
            'enquiry' => (clone $cmpQuery)->whereNull('final_status')
                ->whereNotIn('status', ['invalid', 'irrelevant'])
                ->whereHas('enquiry')->whereDoesntHave('caseFiles')->count(),
            'verification_submitted' => (clone $cmpQuery)->whereNull('final_status')
                ->whereDoesntHave('enquiry')
                ->whereHas('verification', fn ($q) => $q->whereIn('status', ['submitted', 'approved']))
                ->count(),
            'verification' => (clone $cmpQuery)->whereNull('final_status')
                ->whereDoesntHave('enquiry')
                ->whereHas('verification', fn ($q) => $q->whereNotIn('status', ['submitted', 'approved', 'closed']))
                ->count(),
            'scrutiny_complete' => (clone $cmpQuery)->where('status', 'complete')->whereNotNull('tracking_no')
                ->whereNull('final_status')
                ->whereDoesntHave('verification')->count(),
            'registered' => (clone $cmpQuery)->where(function ($q) {
                $q->where('status', 'incomplete')->orWhereNull('tracking_no');
            })->whereNull('final_status')->whereDoesntHave('verification')->count(),
        ];
    }

    public function avgCompletion(array $workflowStages): int
    {
        $stageWeights = [
            'registered' => 10, 'scrutiny_complete' => 25, 'verification' => 40,
            'verification_submitted' => 60, 'enquiry' => 80, 'case_filed' => 90, 'resolved' => 100,
        ];
        $weighted = 0;
        $stageTotal = 0;
        foreach ($workflowStages as $k => $n) {
            $weighted += $n * ($stageWeights[$k] ?? 0);
            $stageTotal += $n;
        }

        return $stageTotal > 0 ? (int) round($weighted / $stageTotal) : 0;
    }

    public function caseOutcomesFromStages(array $workflowStages): array
    {
        $buckets = [
            'Resolved' => $workflowStages['resolved'] ?? 0,
            'Case Filed' => $workflowStages['case_filed'] ?? 0,
            'Enquiry Stage' => $workflowStages['enquiry'] ?? 0,
            'Verification Stage' => ($workflowStages['verification'] ?? 0) + ($workflowStages['verification_submitted'] ?? 0),
            'Registered' => ($workflowStages['registered'] ?? 0) + ($workflowStages['scrutiny_complete'] ?? 0),
        ];

        $total = max(1, array_sum($buckets));

        return collect($buckets)->map(fn ($count, $outcome) => [
            'outcome' => $outcome,
            'count' => $count,
            'percentage' => (int) round(($count / $total) * 100),
        ])->values()->all();
    }

    public function monthlyTrends(Builder $cmpQuery, Builder $enqQuery, int $year): array
    {
        $receivedMap = (clone $cmpQuery)->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as m, COUNT(*) as c')
            ->groupBy('m')->pluck('c', 'm');
        $resolvedMap = (clone $cmpQuery)
            ->where(function ($q) {
                $q->whereNotNull('final_status')->orWhereIn('status', ['invalid', 'irrelevant']);
            })
            ->whereYear('updated_at', $year)
            ->selectRaw('MONTH(updated_at) as m, COUNT(*) as c')
            ->groupBy('m')->pluck('c', 'm');
        $convertedMap = (clone $enqQuery)->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as m, COUNT(*) as c')
            ->groupBy('m')->pluck('c', 'm');

        $monthlyTrends = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyTrends[] = [
                'month' => date('M', mktime(0, 0, 0, $m, 1)),
                'received' => (int) ($receivedMap[$m] ?? 0),
                'resolved' => (int) ($resolvedMap[$m] ?? 0),
                'converted' => (int) ($convertedMap[$m] ?? 0),
            ];
        }

        return $monthlyTrends;
    }

    public function categoryBreakdown(Builder $cmpQuery): \Illuminate\Support\Collection
    {
        $categoryColors = [
            'Financial / Banking Fraud' => '#FDDF00',
            'Cyberstalking' => '#264078',
            'Hacking / Unauthorized Access' => '#267859',
            'Impersonation / Identity Theft' => '#9b3232',
        ];

        $categoryBreakdown = OffenceType::query()
            ->selectRaw('offence_types.value, offence_types.name, COUNT(*) as total')
            ->join('complaints', 'offence_types.value', '=', 'complaints.offence_type')
            ->whereIn('complaints.id', (clone $cmpQuery)->select('id'))
            ->groupBy('offence_types.value', 'offence_types.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        $totalCategorized = $categoryBreakdown->sum('total');

        return $categoryBreakdown->map(function ($item) use ($categoryColors, $totalCategorized) {
            $item->color = $categoryColors[$item->name] ?? '#1950c7';
            $item->percentage = $totalCategorized > 0 ? (int) round(($item->total / $totalCategorized) * 100) : 0;

            return $item;
        });
    }

    public function roleMetrics(?User $user, Builder $enqQuery, Builder $caseQuery, Builder $verQuery): array
    {
        $courtQuery = $user ? CourtCase::visibleTo($user) : CourtCase::query();

        $unservedSummons = (clone $enqQuery)->whereHas('notices', fn ($q) => $q->whereIn('status', ['issued', 'unserved']))->count();
        $courtHearingsUpcoming = (clone $courtQuery)->whereNotIn('status', ['verdict_given', 'closed', 'dismissed'])->count();
        $pendingApprovals = (clone $verQuery)->where('status', 'submitted')->count()
            + (clone $enqQuery)->where('status', 'cfr_submitted')->count()
            + (clone $caseQuery)->where('status', 'cfr_submitted')->count();

        return compact('unservedSummons', 'courtHearingsUpcoming', 'pendingApprovals');
    }
}

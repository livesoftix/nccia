<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Circle;
use App\Models\Complaint;
use App\Models\CourtCase;
use App\Models\DoLetter;
use App\Models\DsrReport;
use App\Models\Enquiry;
use App\Models\OffenceType;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DepartmentProgressController extends Controller
{
    private const ALLOWED_ROLES = [
        'director_general',
        'additional_director',
        'dd_legal',
        'ad_legal',
        'admin',
        'circle_incharge',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Strict Role Security: DG, Additional Director, DD Legal, AD Legal, Admin, Circle Incharge
        if (!$user || !$user->hasAnyRole(self::ALLOWED_ROLES)) {
            return response()->json([
                'message' => 'Unauthorized. This monitoring dashboard is restricted to Executive, Legal and Circle Incharge officers only.'
            ], 403);
        }

        $year = (int) $request->input('year', now()->year);
        $circleId = $request->input('circle_id') ? (int) $request->input('circle_id') : null;

        $isCi = $user->hasRole('circle_incharge') || ($user->role ?? '') === 'circle_incharge';
        $isStationOfficer = !$user->isHeadquarters() && !$user->isZonalHead();

        // Regional circles/incharges are strictly locked to their own circle. Only Islamabad HQ / Admin / DG can see all circles.
        if ($isCi || $isStationOfficer) {
            $circleId = (int) ($user->circle_id ?: Circle::where('code', 'LHR')->value('id'));
        } elseif ($user->isZonalHead()) {
            $effectiveZoneId = $user->zone_id ?: $user->circle?->zone_id;
            if ($circleId) {
                $targetCircle = Circle::find($circleId);
                if (!$targetCircle || (int) $targetCircle->zone_id !== (int) $effectiveZoneId) {
                    $circleId = null;
                }
            }
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $cacheKey = "dept_prog:v5:{$user->id}:{$year}:" . ($circleId ?: 'all') . ":{$dateFrom}:{$dateTo}";

        $payload = Cache::remember($cacheKey, 60, function () use ($year, $circleId, $dateFrom, $dateTo, $user) {
            return $this->buildProgressData($year, $circleId, $dateFrom, $dateTo, $user);
        });

        return response()->json($payload);
    }

    private function buildProgressData(int $year, ?int $circleId, ?string $dateFrom, ?string $dateTo, ?User $user = null): array
    {
        $isStationLocked = $user && ($user->hasRole('circle_incharge') || ($user->role ?? '') === 'circle_incharge' || (!$user->isHeadquarters() && !$user->isZonalHead()));

        // 1. Fetch authorized Circles only
        if ($isStationLocked && $circleId) {
            $circles = Circle::query()->select('id', 'name', 'code')->where('id', $circleId)->get();
        } elseif ($user && $user->isZonalHead()) {
            $effectiveZoneId = $user->zone_id ?: $user->circle?->zone_id;
            $circles = Circle::query()->select('id', 'name', 'code')->where('zone_id', $effectiveZoneId)->orderBy('name')->get();
        } else {
            $circles = Circle::query()->select('id', 'name', 'code')->orderBy('name')->get();
        }

        // 2. Base queries with optional date / year filtering
        $cmpQuery = Complaint::query();
        $enqQuery = Enquiry::query();
        $caseQuery = CaseFile::query();
        $courtQuery = CourtCase::query();

        if ($dateFrom) {
            $cmpQuery->whereDate('created_at', '>=', $dateFrom);
            $enqQuery->whereDate('created_at', '>=', $dateFrom);
            $caseQuery->whereDate('created_at', '>=', $dateFrom);
            $courtQuery->whereDate('created_at', '>=', $dateFrom);
        } elseif ($year) {
            $cmpQuery->whereYear('created_at', $year);
            $enqQuery->whereYear('created_at', $year);
            $caseQuery->whereYear('created_at', $year);
            $courtQuery->whereYear('created_at', $year);
        }

        if ($dateTo) {
            $cmpQuery->whereDate('created_at', '<=', $dateTo);
            $enqQuery->whereDate('created_at', '<=', $dateTo);
            $caseQuery->whereDate('created_at', '<=', $dateTo);
            $courtQuery->whereDate('created_at', '<=', $dateTo);
        }

        // If a specific circle is chosen
        if ($circleId) {
            $cmpQuery->where('circle_id', $circleId);
            $enqQuery->where(function ($q) use ($circleId) {
                $q->whereHas('complaint', fn ($sq) => $sq->where('circle_id', $circleId))
                  ->orWhere('direct_info->circle_id', $circleId)
                  ->orWhere('direct_info->circle_id', (string) $circleId);
            });
            $caseQuery->where(function ($q) use ($circleId) {
                $q->whereHas('enquiry.complaint', fn ($sq) => $sq->where('circle_id', $circleId))
                  ->orWhere('direct_info->circle_id', $circleId)
                  ->orWhere('direct_info->circle_id', (string) $circleId);
            });
        }

        // 3. Overall KPI Metrics for selected scope
        $totalComplaints = (clone $cmpQuery)->count();
        $resolvedComplaints = (clone $cmpQuery)
            ->where(function ($q) {
                $q->whereNotNull('final_status')
                  ->orWhereIn('status', ['complete', 'invalid', 'irrelevant']);
            })->count();

        $activeEnquiries = (clone $enqQuery)
            ->whereNotIn('status', ['approved', 'closed'])
            ->count();

        $totalEnquiries = (clone $enqQuery)->count();

        $registeredCases = (clone $caseQuery)->count();
        $finalizedCases = (clone $caseQuery)
            ->where('status', 'closed')
            ->count();

        $totalCourtCases = (clone $courtQuery)->count();
        $activeCourtCases = (clone $courtQuery)
            ->whereNotIn('status', ['verdict_given', 'closed', 'dismissed'])
            ->count();

        // Legal Scrutiny & CFRs pending approval (for Legal Chain & DG)
        $legalStatuses = ['cfr_submitted', 'legal_review_dd', 'legal_review_ad', 'legal_review_dg'];
        $pendingLegalEnquiries = (clone $enqQuery)->whereIn('status', $legalStatuses)->count();
        $pendingLegalCases = (clone $caseQuery)->whereIn('status', $legalStatuses)->count();
        $pendingLegalReviews = $pendingLegalEnquiries + $pendingLegalCases;

        // Overall Disposal calculation
        $totalWorkload = $totalComplaints + $totalEnquiries + $registeredCases;
        $totalDisposed = $resolvedComplaints + $finalizedCases;
        $overallDisposalRate = $totalWorkload > 0 ? (int) round(($totalDisposed / max(1, $totalComplaints + $registeredCases)) * 100) : 0;
        $overallDisposalRate = min(100, $overallDisposalRate);

        // 4. Circle-by-Circle Detailed Breakdown
        $officersCountByCircle = User::whereNotNull('circle_id')
            ->selectRaw('circle_id, count(*) as count')
            ->groupBy('circle_id')
            ->pluck('count', 'circle_id');

        $circleBreakdown = [];
        $circleBarChart = [];

        foreach ($circles as $c) {
            $cCmpTotal = Complaint::where('circle_id', $c->id)
                ->when($year, fn ($q) => $q->whereYear('created_at', $year))
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->count();

            $cCmpResolved = Complaint::where('circle_id', $c->id)
                ->when($year, fn ($q) => $q->whereYear('created_at', $year))
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->where(function ($q) {
                    $q->whereNotNull('final_status')
                      ->orWhereIn('status', ['complete', 'invalid', 'irrelevant']);
                })->count();

            $cEnqTotal = Enquiry::where(function ($q) use ($c) {
                    $q->whereHas('complaint', fn ($sq) => $sq->where('circle_id', $c->id))
                      ->orWhere('direct_info->circle_id', $c->id)
                      ->orWhere('direct_info->circle_id', (string) $c->id);
                })
                ->when($year, fn ($q) => $q->whereYear('created_at', $year))
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->count();

            $cCasesTotal = CaseFile::where(function ($q) use ($c) {
                    $q->whereHas('enquiry.complaint', fn ($sq) => $sq->where('circle_id', $c->id))
                      ->orWhere('direct_info->circle_id', $c->id)
                      ->orWhere('direct_info->circle_id', (string) $c->id);
                })
                ->when($year, fn ($q) => $q->whereYear('created_at', $year))
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->count();

            $cCasesFinalized = CaseFile::where(function ($q) use ($c) {
                    $q->whereHas('enquiry.complaint', fn ($sq) => $sq->where('circle_id', $c->id))
                      ->orWhere('direct_info->circle_id', $c->id)
                      ->orWhere('direct_info->circle_id', (string) $c->id);
                })
                ->when($year, fn ($q) => $q->whereYear('created_at', $year))
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->where('status', 'closed')
                ->count();

            $cLegalPending = Enquiry::where(function ($q) use ($c) {
                    $q->whereHas('complaint', fn ($sq) => $sq->where('circle_id', $c->id))
                      ->orWhere('direct_info->circle_id', $c->id)
                      ->orWhere('direct_info->circle_id', (string) $c->id);
                })
                ->whereIn('status', $legalStatuses)
                ->count() + CaseFile::where(function ($q) use ($c) {
                    $q->whereHas('enquiry.complaint', fn ($sq) => $sq->where('circle_id', $c->id))
                      ->orWhere('direct_info->circle_id', $c->id)
                      ->orWhere('direct_info->circle_id', (string) $c->id);
                })
                ->whereIn('status', $legalStatuses)
                ->count();

            $cTotalAll = $cCmpTotal + $cCasesTotal;
            $cResolvedAll = $cCmpResolved + $cCasesFinalized;
            $cRate = $cTotalAll > 0 ? (int) round(($cResolvedAll / $cTotalAll) * 100) : 0;
            $cRate = min(100, $cRate);

            $badge = 'low';
            if ($cRate >= 70) {
                $badge = 'high';
            } elseif ($cRate >= 40) {
                $badge = 'medium';
            }

            $item = [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'officers_count' => (int) ($officersCountByCircle[$c->id] ?? 0),
                'total_complaints' => $cCmpTotal,
                'resolved_complaints' => $cCmpResolved,
                'total_enquiries' => $cEnqTotal,
                'total_cases' => $cCasesTotal,
                'finalized_cases' => $cCasesFinalized,
                'pending_legal' => $cLegalPending,
                'disposal_rate' => $cRate,
                'status_badge' => $badge,
            ];

            $circleBreakdown[] = $item;

            // Bar chart data
            $circleBarChart[] = [
                'name' => $c->name,
                'code' => $c->code ?: $c->name,
                'total' => $cCmpTotal + $cEnqTotal + $cCasesTotal,
                'disposed' => $cCmpResolved + $cCasesFinalized,
                'pending' => max(0, ($cCmpTotal + $cEnqTotal + $cCasesTotal) - ($cCmpResolved + $cCasesFinalized)),
                'rate' => $cRate,
            ];
        }

        // Sort circle breakdown by total volume descending
        usort($circleBreakdown, fn ($a, $b) => ($b['total_complaints'] + $b['total_enquiries']) <=> ($a['total_complaints'] + $a['total_enquiries']));
        usort($circleBarChart, fn ($a, $b) => $b['total'] <=> $a['total']);

        // 5. Circle-wise Officers Directory & Individual Workload
        $officersQuery = User::query()
            ->with(['circle:id,name,code', 'roles:id,name'])
            ->select('id', 'name', 'email', 'phone', 'role', 'designation', 'circle_id', 'status', 'status_remarks');

        if ($circleId) {
            $officersQuery->where('circle_id', $circleId);
        }

        $officers = $officersQuery->get();

        $enquiryCounts = Enquiry::selectRaw('enquiry_officer_id, count(*) as total, sum(case when status in (\'approved\', \'closed\') then 1 else 0 end) as completed')
            ->whereNotNull('enquiry_officer_id')
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->groupBy('enquiry_officer_id')
            ->get()
            ->keyBy('enquiry_officer_id');

        $caseCounts = CaseFile::selectRaw('investigation_officer_id, count(*) as total, sum(case when status = \'closed\' then 1 else 0 end) as completed')
            ->whereNotNull('investigation_officer_id')
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->groupBy('investigation_officer_id')
            ->get()
            ->keyBy('investigation_officer_id');

        $verificationCounts = Verification::selectRaw('verification_officer_id, count(*) as total, sum(case when status in (\'submitted\', \'approved\') then 1 else 0 end) as completed')
            ->whereNotNull('verification_officer_id')
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->groupBy('verification_officer_id')
            ->get()
            ->keyBy('verification_officer_id');

        $circleOfficers = $officers->map(function ($u) use ($enquiryCounts, $caseCounts, $verificationCounts) {
            $roleName = $u->roles->first()?->name ?? $u->role ?? 'Officer';
            $enqStat = $enquiryCounts->get($u->id);
            $caseStat = $caseCounts->get($u->id);
            $verStat = $verificationCounts->get($u->id);

            $assigned = ($enqStat->total ?? 0) + ($caseStat->total ?? 0) + ($verStat->total ?? 0);
            $completed = ($enqStat->completed ?? 0) + ($caseStat->completed ?? 0) + ($verStat->completed ?? 0);
            $pending = max(0, $assigned - $completed);
            $rate = $assigned > 0 ? (int) round(($completed / $assigned) * 100) : 0;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'circle_id' => $u->circle_id,
                'circle_name' => $u->circle?->name ?: 'Islamabad HQ / National',
                'circle_code' => $u->circle?->code ?: 'HQ',
                'role' => $roleName,
                'role_label' => ucwords(str_replace('_', ' ', $roleName)),
                'designation' => $u->designation ?: ucwords(str_replace('_', ' ', $roleName)),
                'assigned_workload' => $assigned,
                'completed_workload' => $completed,
                'pending_workload' => $pending,
                'completion_rate' => $rate,
                'status' => $u->status ?: 'active',
                'status_remarks' => $u->status_remarks,
            ];
        })->sortByDesc('assigned_workload')->values();

        // 6. Selected Circle Info (for dedicated separate circle portal)
        $selectedCircleInfo = null;
        if ($circleId) {
            $cModel = Circle::with('zone:id,name,code')->find($circleId);
            if ($cModel) {
                $incharge = User::where('circle_id', $circleId)
                    ->where(function ($q) {
                        $q->where('role', 'circle_incharge')
                          ->orWhereHas('roles', fn ($sq) => $sq->where('name', 'circle_incharge'));
                    })->first();

                $selectedCircleInfo = [
                    'id' => $cModel->id,
                    'name' => $cModel->name,
                    'code' => $cModel->code,
                    'zone_name' => $cModel->zone?->name ?: 'Regional Zone',
                    'zone_code' => $cModel->zone?->code ?: 'RZ',
                    'incharge_name' => $incharge?->name ?: 'Circle Incharge / Designated Officer',
                    'incharge_email' => $incharge?->email ?: 'incharge.' . strtolower($cModel->code ?: 'circle') . '@nccia.gov.pk',
                    'incharge_phone' => $incharge?->phone ?: 'Official Helpdesk',
                    'incharge_designation' => $incharge?->designation ?: 'Circle Incharge / Deputy Director',
                    'total_officers' => count($circleOfficers),
                ];
            }
        }

        // 7. Pie Chart Data: Workflow Stage Distribution (Scoped)
        $workflowPie = [
            [
                'name' => 'Complaints (CMU)',
                'count' => max(0, $totalComplaints - $resolvedComplaints),
                'color' => '#0284c7',
            ],
            [
                'name' => 'Active Enquiries',
                'count' => $activeEnquiries,
                'color' => '#f59e0b',
            ],
            [
                'name' => 'Active Cases (FIR)',
                'count' => max(0, $registeredCases - $finalizedCases),
                'color' => '#ef4444',
            ],
            [
                'name' => 'Court Trials',
                'count' => $activeCourtCases,
                'color' => '#8b5cf6',
            ],
            [
                'name' => 'Disposed / Closed',
                'count' => $totalDisposed,
                'color' => '#10b981',
            ],
        ];

        // 8. Pie Chart / Bar Data: Offence Categories Breakdown (Scoped)
        $categoryBreakdown = OffenceType::query()
            ->selectRaw('offence_types.name, COUNT(*) as count')
            ->join('complaints', 'offence_types.value', '=', 'complaints.offence_type')
            ->whereIn('complaints.id', (clone $cmpQuery)->select('id'))
            ->groupBy('offence_types.name')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->map(function ($row, $i) {
                $palette = ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0891b2'];
                return [
                    'name' => $row->name,
                    'count' => (int) $row->count,
                    'color' => $palette[$i % count($palette)],
                ];
            });

        // 9. Monthly Trends (Inflow vs Disposal - Scoped)
        $monthlyTrends = [];
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', created_at) AS INTEGER)" : "MONTH(created_at)";
        $updatedMonthExpr = $isSqlite ? "CAST(strftime('%m', updated_at) AS INTEGER)" : "MONTH(updated_at)";

        $receivedMonths = (clone $cmpQuery)
            ->selectRaw("{$monthExpr} as m, COUNT(*) as c")
            ->groupBy('m')
            ->pluck('c', 'm');

        $resolvedMonths = (clone $cmpQuery)
            ->where(function ($q) {
                $q->whereNotNull('final_status')
                  ->orWhereIn('status', ['complete', 'invalid', 'irrelevant']);
            })
            ->selectRaw("{$updatedMonthExpr} as m, COUNT(*) as c")
            ->groupBy('m')
            ->pluck('c', 'm');

        for ($m = 1; $m <= 12; $m++) {
            $monthlyTrends[] = [
                'month' => date('M', mktime(0, 0, 0, $m, 1)),
                'received' => (int) ($receivedMonths[$m] ?? 0),
                'resolved' => (int) ($resolvedMonths[$m] ?? 0),
            ];
        }

        // 10. Legal Review Backlog Items (CFRs & Opinions for AD/DD/DG)
        $legalBacklog = Enquiry::whereIn('status', $legalStatuses)
            ->where(function ($q) use ($circleId) {
                if ($circleId) {
                    $q->whereHas('complaint', fn ($sq) => $sq->where('circle_id', $circleId))
                      ->orWhere('direct_info->circle_id', $circleId);
                }
            })
            ->with(['complaint.circle'])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'type' => 'Enquiry CFR',
                'number' => $e->enquiry_no ?: ("ENQ-" . str_pad($e->id, 5, '0', STR_PAD_LEFT)),
                'circle' => $e->complaint?->circle?->name ?: 'Circle Unit',
                'status' => $e->status,
                'updated_at' => $e->updated_at ? $e->updated_at->format('d M Y') : 'N/A',
            ]);

        // 11. DSR Reports (Scoped to circle or nationwide HQ)
        $hqDsrFeed = [];
        $pendingDsrCount = 0;
        if (Schema::hasTable('dsr_reports')) {
            $dsrQuery = DsrReport::query();
            if ($circleId) {
                $dsrQuery->where('circle_id', $circleId);
            } else {
                $dsrQuery->whereIn('status', [DsrReport::STATUS_FORWARDED_HQ, DsrReport::STATUS_HQ_ACK]);
            }
            $pendingDsrCount = (clone $dsrQuery)->where('status', DsrReport::STATUS_FORWARDED_HQ)->count();
            $hqDsrFeed = $dsrQuery
                ->with(['circle:id,name,code', 'compiler:id,name'])
                ->latest('report_date')
                ->limit(6)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'circle_name' => $r->circle?->name ?: 'Circle Unit',
                    'circle_code' => $r->circle?->code ?: '',
                    'report_date' => $r->report_date ? Carbon::parse($r->report_date)->format('d M Y') : 'N/A',
                    'unit_name' => $r->unit_name ?: 'Circle Operations',
                    'status' => $r->status,
                    'is_pending_ack' => $r->status === DsrReport::STATUS_FORWARDED_HQ,
                    'status_label' => $r->status === DsrReport::STATUS_FORWARDED_HQ ? 'Pending HQ Acknowledgment' : ($r->status === DsrReport::STATUS_HQ_ACK ? 'HQ Acknowledged' : ucwords(str_replace('_', ' ', $r->status))),
                    'forwarded_at' => $r->forwarded_to_hq_at ? Carbon::parse($r->forwarded_to_hq_at)->format('d M Y, h:i A') : ($r->created_at ? $r->created_at->format('d M Y') : 'N/A'),
                ]);
        }

        // 12. D.O. Letters (Scoped to circle or nationwide HQ)
        $hqDoFeed = [];
        $pendingDoCount = 0;
        if (Schema::hasTable('do_letters')) {
            $doQuery = DoLetter::query();
            if ($circleId) {
                $doQuery->where('circle_id', $circleId);
            } else {
                $doQuery->whereIn('status', [DoLetter::STATUS_FORWARDED_HQ, DoLetter::STATUS_HQ_ACK]);
            }
            $pendingDoCount = (clone $doQuery)->where('status', DoLetter::STATUS_FORWARDED_HQ)->count();
            $hqDoFeed = $doQuery
                ->with(['circle:id,name,code', 'compiler:id,name'])
                ->latest('report_month')
                ->limit(6)
                ->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'circle_name' => $l->circle?->name ?: 'Circle Unit',
                    'circle_code' => $l->circle?->code ?: '',
                    'month_label' => $l->month_label ?: ($l->report_month ? Carbon::parse($l->report_month)->format('F Y') : 'Monthly D.O.'),
                    'status' => $l->status,
                    'is_pending_ack' => $l->status === DoLetter::STATUS_FORWARDED_HQ,
                    'status_label' => $l->status === DoLetter::STATUS_FORWARDED_HQ ? 'Awaiting DG Review' : ($l->status === DoLetter::STATUS_HQ_ACK ? 'Acknowledged by DG' : ucwords(str_replace('_', ' ', $l->status))),
                    'forwarded_at' => $l->forwarded_to_hq_at ? Carbon::parse($l->forwarded_to_hq_at)->format('d M Y, h:i A') : ($l->created_at ? $l->created_at->format('d M Y') : 'N/A'),
                ]);
        }

        // 13. Inter-Circle Transfers (Scoped or Nationwide)
        $transfersQuery = Complaint::whereNotNull('transfer_to_circle_id');
        if ($circleId) {
            $transfersQuery->where(function ($q) use ($circleId) {
                $q->where('circle_id', $circleId)
                  ->orWhere('transfer_to_circle_id', $circleId);
            });
        }
        $transfersFeed = $transfersQuery
            ->with(['circle:id,name,code', 'transferToCircle:id,name,code'])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn ($cmp) => [
                'id' => $cmp->id,
                'complaint_no' => $cmp->complaint_no ?: ('CMP-' . str_pad($cmp->id, 5, '0', STR_PAD_LEFT)),
                'from_circle' => $cmp->circle?->name ?: 'Origin Circle',
                'to_circle' => $cmp->transferToCircle?->name ?: 'Target Circle',
                'transferred_at' => $cmp->updated_at ? $cmp->updated_at->format('d M Y') : 'N/A',
            ]);

        $totalTransfersCount = (clone $transfersQuery)->count();

        return [
            'metrics' => [
                'total_workload' => $totalWorkload,
                'total_complaints' => $totalComplaints,
                'resolved_complaints' => $resolvedComplaints,
                'total_enquiries' => $totalEnquiries,
                'active_enquiries' => $activeEnquiries,
                'registered_cases' => $registeredCases,
                'finalized_cases' => $finalizedCases,
                'total_court_cases' => $totalCourtCases,
                'pending_legal_reviews' => $pendingLegalReviews,
                'overall_disposal_rate' => $overallDisposalRate,
            ],
            'hq_command' => [
                'headquarters' => $isStationLocked ? (($selectedCircleInfo['name'] ?? 'Circle') . ' Command Portal') : 'Islamabad Headquarters (HQ)',
                'jurisdiction' => $isStationLocked ? (($selectedCircleInfo['name'] ?? 'Local Circle') . ' Operational Jurisdiction') : 'Nationwide / Federal Territory & Provincial Circles',
                'total_circles' => count($circles),
                'total_officers' => count($circleOfficers),
                'pending_dsr' => $pendingDsrCount,
                'pending_do' => $pendingDoCount,
                'total_transfers' => $totalTransfersCount,
            ],
            'is_station_locked' => (bool) $isStationLocked,
            'user_station_name' => $user?->circle?->name,
            'user_circle_code'  => $user?->circle?->code,
            'is_zonal_head'     => $user?->isZonalHead() ?? false,
            'selected_circle' => $selectedCircleInfo,
            'circles' => $circles,
            'circle_breakdown' => $circleBreakdown,
            'circle_bar_chart' => array_slice($circleBarChart, 0, 10),
            'circle_officers' => $circleOfficers,
            'workflow_pie' => $workflowPie,
            'category_breakdown' => $categoryBreakdown,
            'monthly_trends' => $monthlyTrends,
            'legal_backlog' => $legalBacklog,
            'hq_dsr_feed' => $hqDsrFeed,
            'hq_do_feed' => $hqDoFeed,
            'transfers_feed' => $transfersFeed,
            'filters' => [
                'year' => $year,
                'circle_id' => $circleId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ];
    }
}

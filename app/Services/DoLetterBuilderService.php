<?php

namespace App\Services;

use App\Models\Arrest;
use App\Models\CaseActivity;
use App\Models\CaseFile;
use App\Models\Circle;
use App\Models\Complaint;
use App\Models\CourtCase;
use App\Models\CourtVerdict;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Models\ForensicRequest;
use App\Models\Verification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DoLetterBuilderService
{
    public function __construct(
        private AdminReportStatsService $stats,
    ) {}

    public function build(int $circleId, Carbon $month): array
    {
        $circle = Circle::find($circleId);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $label = $start->format('F-Y');

        $casesAdded = $this->caseBase($circleId)->whereBetween('created_at', [$start, $end])->count();
        $casesClosed = $this->caseBase($circleId)
            ->where(function ($q) {
                $q->where('status', 'closed')->orWhere('status', 'like', 'closed_%');
            })
            ->whereBetween('updated_at', [$start, $end])
            ->count();
        $casesPending = $this->caseBase($circleId)->whereNotIn('status', ['closed'])->count();

        $enquiriesAdded = $this->enquiryBase($circleId)->whereBetween('created_at', [$start, $end])->count();
        $enquiriesClosed = $this->enquiryBase($circleId)
            ->whereIn('status', ['closed', 'approved'])
            ->whereBetween('updated_at', [$start, $end])
            ->count();
        $enquiriesPending = $this->enquiryBase($circleId)
            ->whereNotIn('status', ['closed', 'approved', 'converted_to_case'])
            ->count();

        $disposals = $this->stats->caseDisposalsForRange($circleId, $start, $end);
        $raidBase = $this->stats->raidStatsForRange($circleId, $start, $end);
        $poStats = array_merge($this->stats->poStatsForRange($circleId, $start, $end), ['deleted' => 0]);
        $caStats = array_merge($this->stats->caStatsForRange($circleId, $start, $end), ['deleted' => 0]);

        $casesCfrTotal = Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereIn('status', ['cfr_submitted', 'legal_review_dd', 'legal_review_ad', 'legal_review_dg'])
            ->count();

        $casesPendingLegal = Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereIn('status', ['legal_review_dd', 'legal_review_ad', 'cfr_submitted'])
            ->count();

        $casesPendingForensic = ForensicRequest::query()
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereNotIn('status', ['handed_over'])
            ->count();

        $enquiriesConvertedCase = $this->enquiryBase($circleId)
            ->whereIn('status', ['converted_to_case'])
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        $enquiriesTransferred = $this->enquiryBase($circleId)
            ->where('status', 'transferred')
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        $verificationsAdded = Verification::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $verificationsPending = Verification::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereNotIn('status', ['approved', 'closed', 'merged', 'transferred'])
            ->count();

        $complaintsAdded = Complaint::query()
            ->where('circle_id', $circleId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $complaintsPending = Complaint::query()
            ->where('circle_id', $circleId)
            ->whereNull('final_status')
            ->whereNotIn('status', ['invalid', 'irrelevant', 'closed', 'merged', 'transferred'])
            ->count();

        $convictions = $this->convictionsForMonth($circleId, $start, $end);
        $recoveries = $this->recoveriesForMonth($circleId, $start, $end);
        $courtWork = $this->courtWorkForMonth($circleId, $start, $end);

        $arrestedList = Arrest::query()
            ->with(['caseFile.enquiry.complaint'])
            ->whereBetween('arrest_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->latest('arrest_date')
            ->limit(48)
            ->get()
            ->map(fn (Arrest $a, $i) => [
                'sr' => $i + 1,
                'fir_no' => $a->caseFile?->fir_no,
                'accused_name' => $a->accused_name,
                'arrest_date' => optional($a->arrest_date)->format('d.m.Y'),
                'address' => $a->caseFile?->enquiry?->complaint?->address,
            ])
            ->values()
            ->all();

        return [
            'month_label' => $label,
            'report_month' => $start->toDateString(),
            'circle_name' => $circle?->name,
            'payload' => [
                'cases' => [
                    'at_beginning' => max(0, $casesPending - $casesAdded + $casesClosed),
                    'added' => $casesAdded,
                    'total' => $casesAdded,
                    'interim_challan' => $disposals['interim_challan'],
                    'final_challan' => $disposals['final_challan'],
                    'discharge' => $disposals['discharge'],
                    'transferred' => $disposals['transferred'],
                    'merged' => $disposals['merged'],
                    'pending' => $casesPending,
                ],
                'cases_cfr_pendency' => [
                    'total_cfrs' => $casesCfrTotal,
                    'pending_ad_dd_legal' => $casesPendingLegal,
                    'pending_forensic' => $casesPendingForensic,
                    'pending_addl_director' => Enquiry::query()
                        ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
                        ->where('status', 'legal_review_dg')
                        ->count(),
                    'pending_dd_office' => 0,
                    'pending_dir_ccw_isb' => 0,
                    'pending_dir_ops' => 0,
                    'pending_investigation' => $casesPending,
                    'total_pending' => $casesPending,
                ],
                'cfr_pendency' => [
                    'total_cfrs' => $casesCfrTotal,
                    'pending_legal' => $casesPendingLegal,
                    'pending_forensic' => $casesPendingForensic,
                    'pending_investigation' => $casesPending,
                    'total_pending' => $casesPending + $enquiriesPending,
                ],
                'enquiries' => [
                    'at_beginning' => max(0, $enquiriesPending - $enquiriesAdded + $enquiriesClosed),
                    'added' => $enquiriesAdded,
                    'total' => $enquiriesAdded,
                    'converted_case' => $enquiriesConvertedCase,
                    'converted_qalandra' => $this->enquiryBase($circleId)
                        ->where('recommendation', 'irrelevant')
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'closed' => $enquiriesClosed,
                    'transferred' => $enquiriesTransferred,
                    'merged' => $this->enquiryBase($circleId)
                        ->where('recommendation', 'merge')
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'pending' => $enquiriesPending,
                ],
                'enquiries_cfr_pendency' => [
                    'total_cfrs' => $casesCfrTotal,
                    'pending_ad_dd_legal' => $casesPendingLegal,
                    'pending_forensic' => $casesPendingForensic,
                    'pending_addl_director' => 0,
                    'pending_dd_law' => Enquiry::query()
                        ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
                        ->where('status', 'legal_review_dd')
                        ->count(),
                    'pending_dir_ccw_isb' => 0,
                    'pending_dy_director_ccrc' => 0,
                    'pending_probe' => $enquiriesPending,
                    'total_pending' => $enquiriesPending,
                ],
                'verifications' => [
                    'at_beginning' => max(0, $verificationsPending - $verificationsAdded),
                    'added' => $verificationsAdded,
                    'total' => $verificationsAdded,
                    'converted_enquiries' => Complaint::query()
                        ->where('circle_id', $circleId)
                        ->whereHas('enquiries')
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'converted_case' => 0,
                    'closed' => Verification::query()
                        ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
                        ->whereIn('status', ['closed', 'approved'])
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'transferred' => Verification::query()
                        ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
                        ->where('status', 'transferred')
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'merged' => Verification::query()
                        ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
                        ->where('status', 'merged')
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'pending' => $verificationsPending,
                ],
                'complaints' => [
                    'at_beginning' => max(0, $complaintsPending - $complaintsAdded),
                    'added' => $complaintsAdded,
                    'total' => $complaintsAdded,
                    'converted_verification' => Complaint::query()
                        ->where('circle_id', $circleId)
                        ->whereHas('verification')
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'invalid' => Complaint::query()
                        ->where('circle_id', $circleId)
                        ->whereIn('status', ['invalid', 'irrelevant'])
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'closed' => Complaint::query()
                        ->where('circle_id', $circleId)
                        ->whereNotNull('final_status')
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'transferred' => Complaint::query()
                        ->where('circle_id', $circleId)
                        ->where('final_status', 'transferred')
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'merged' => Complaint::query()
                        ->where('circle_id', $circleId)
                        ->where('final_status', 'merged')
                        ->whereBetween('updated_at', [$start, $end])
                        ->count(),
                    'pending' => $complaintsPending,
                ],
                'aml' => [
                    'cases_registered' => $casesAdded,
                    'amla_added' => CaseActivity::query()
                        ->where('type', 'bank_record')
                        ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
                        ->whereHas('caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
                        ->count(),
                    'accused_arrested' => count($arrestedList),
                    'under_investigation' => $casesPending,
                    'under_trial' => CourtCase::query()
                        ->whereHas('caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
                        ->where('status', '!=', 'closed')
                        ->count(),
                    'total_conviction' => collect($convictions)->where('verdict', 'conviction')->count(),
                    'acquittals' => collect($convictions)->where('verdict', 'acquittal')->count(),
                ],
                'raid' => array_merge($raidBase, [
                    'raid_in_cases' => CaseActivity::query()
                        ->where('type', 'raid')
                        ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
                        ->whereHas('caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
                        ->count(),
                    'enquiries_registered' => $enquiriesAdded,
                    'raid_in_enquiries' => EnquiryActivity::query()
                        ->where('type', 'raid')
                        ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
                        ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
                        ->count(),
                    'cases_challaned' => $disposals['final_challan'] + $disposals['interim_challan'],
                    'pending_end_month' => $casesPending + $enquiriesPending,
                ]),
                'pos' => $poStats,
                'cas' => $caStats,
                'court_work' => $courtWork,
                'convictions' => $convictions,
                'conviction_quality' => $this->convictionQuality($convictions),
                'recoveries' => $recoveries,
                'accused_arrested' => $arrestedList,
                'complaints_registered' => $this->complaintsRegistered($circleId, $start, $end),
            ],
        ];
    }

    /** Complaints registered during the reporting month, with full detail. */
    private function complaintsRegistered(int $circleId, Carbon $start, Carbon $end): array
    {
        return Complaint::query()
            ->where('circle_id', $circleId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('report_date', [$start, $end])
                    ->orWhereBetween('created_at', [$start, $end]);
            })
            ->latest('id')
            ->limit(500)
            ->get()
            ->map(fn (Complaint $c) => [
                'tracking_no' => $c->tracking_no,
                'diary_no' => $c->diary_no,
                'dated' => optional($c->report_date)->format('d.m.Y'),
                'complainant' => $c->complainant_name,
                'cnic' => $c->cnic,
                'contact_no' => $c->contact_no,
                'circle' => $c->circle?->name,
                'offence_type' => $c->offence_type,
                'laws' => $this->formatLaws($c->laws),
                'description' => $c->description,
                'amount_involved' => $c->amount_involved,
                'received_via' => $c->received_via,
                'priority_type' => $c->priority_type,
                'operator_name' => $c->operator_name,
                'scrutiny_result' => $c->scrutiny_result,
                'status' => $c->status,
                'final_status' => $c->final_status,
            ])
            ->values()
            ->all();
    }

    private function formatLaws($laws): string
    {
        if (is_array($laws)) {
            return implode(', ', array_map(fn ($l) => is_array($l) ? (string) ($l['section'] ?? $l['name'] ?? '') : (string) $l, $laws));
        }

        return (string) $laws;
    }

    private function caseBase(int $circleId): Builder
    {
        return CaseFile::query()
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId));
    }

    private function enquiryBase(int $circleId): Builder
    {
        return Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId));
    }

    private function convictionsForMonth(int $circleId, Carbon $start, Carbon $end): array
    {
        return CourtVerdict::query()
            ->with(['courtCase.caseFile.enquiry.complaint'])
            ->whereBetween('verdict_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('courtCase.caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->latest('verdict_date')
            ->limit(7)
            ->get()
            ->map(fn (CourtVerdict $v) => [
                'verdict' => $v->verdict,
                'fir_and_section' => trim(($v->courtCase?->caseFile?->fir_no ?? '') . ' / ' . ($v->courtCase?->caseFile?->enquiry?->complaint?->laws ?? '')),
                'accused_name' => $v->courtCase?->caseFile?->enquiry?->charge_against,
                'court_name' => $v->courtCase?->court_name,
                'order' => $v->details,
                'dated' => optional($v->verdict_date)->format('d.m.Y'),
            ])
            ->values()
            ->all();
    }

    private function convictionQuality(array $convictions): array
    {
        $buckets = [
            'less_than_4_months' => 0,
            '4_to_6_months' => 0,
            '6_months_to_1_year' => 0,
            '1_to_2_years' => 0,
            '2_to_4_years' => 0,
            '4_to_5_years' => 0,
            'more' => 0,
            'only_fine' => 0,
        ];

        return array_merge($buckets, ['total' => count($convictions)]);
    }

    private function recoveriesForMonth(int $circleId, Carbon $start, Carbon $end): array
    {
        $rows = [];

        EnquiryActivity::query()
            ->with(['enquiry.officer:id,name', 'enquiry'])
            ->whereIn('type', ['recoveries', 'recovery'])
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->latest('activity_date')
            ->limit(15)
            ->get()
            ->each(function (EnquiryActivity $act) use (&$rows) {
                $amount = app(AdminReportStatsService::class)->parseAmount($act->description);
                $rows[] = [
                    'enquiry_no_dated' => trim(($act->enquiry?->enquiry_number ?? '') . ' ' . optional($act->activity_date)->format('d.m.Y')),
                    'eo_name' => $act->enquiry?->officer?->name,
                    'amount_recovered' => $amount ? ('PKR ' . number_format($amount, 0)) : '',
                    'deleted' => '',
                    'kind_fine' => '',
                    'kind_cash' => $amount ? ('PKR ' . number_format($amount, 0)) : '',
                ];
            });

        return $rows;
    }

    private function courtWorkForMonth(int $circleId, Carbon $start, Carbon $end): array
    {
        $pending = CourtCase::query()
            ->whereHas('caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->where('status', '!=', 'closed')
            ->count();

        $added = CourtCase::query()
            ->whereHas('caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $convicted = CourtVerdict::query()
            ->where('verdict', 'conviction')
            ->whereBetween('verdict_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('courtCase.caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->count();

        $acquittal = CourtVerdict::query()
            ->whereIn('verdict', ['acquittal', 'discharge'])
            ->whereBetween('verdict_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('courtCase.caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->count();

        return [
            'at_beginning' => max(0, $pending - $added),
            'added' => $added,
            'convicted' => $convicted,
            'acquittal' => $acquittal,
            'cc_to_record' => 0,
            'pending' => max(0, $pending - $convicted - $acquittal),
        ];
    }
}

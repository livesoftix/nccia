<?php

namespace App\Services;

use App\Models\Arrest;
use App\Models\CaseFile;
use App\Models\Circle;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\VerificationReport;
use Carbon\Carbon;

class DsrReportBuilderService
{
    public function __construct(
        private AdminReportStatsService $stats,
    ) {}

    public function build(int $circleId, Carbon $date, ?string $unitName = null): array
    {
        $circle = Circle::find($circleId);
        $unit = $unitName ?: ('CCRC-' . ($circle?->name ?? 'Lahore'));
        $day = $date->toDateString();

        $enquiriesRegistered = $this->enquiriesRegistered($circleId, $day);
        $enquiriesClosed = $this->enquiriesClosed($circleId, $day);
        $casesRegistered = $this->casesRegistered($circleId, $day);
        $casesDisposed = $this->casesDisposed($circleId, $day);
        $arrests = $this->arrestsToday($circleId, $day);
        $complaints = $this->complaintsRegistered($circleId, $day);
        $verifications = $this->verificationsToday($circleId, $day);
        $raid = $this->stats->raidStatsForRange($circleId, $date->copy()->startOfDay(), $date->copy()->endOfDay());

        $poStats = $this->stats->poStatsForRange($circleId, $date->copy()->startOfDay(), $date->copy()->endOfDay());
        $caStats = $this->stats->caStatsForRange($circleId, $date->copy()->startOfDay(), $date->copy()->endOfDay());

        $highlights = [
            'cases' => count($casesRegistered),
            'enquiries_registered' => count($enquiriesRegistered),
            'enquiries_closed' => count($enquiriesClosed),
            'accused_in_lockup' => $this->stats->accusedInLockup($circleId, $date),
            'fresh_arrests' => count($arrests),
            'bail' => $this->stats->bailCountOnDate($circleId, $day),
            'old_accused_arrested' => $this->stats->oldAccusedArrestedOnDate($circleId, $day),
            'conviction' => count(array_filter($casesDisposed, fn ($r) => ($r['reason'] ?? '') === 'conviction')),
            'raids' => $raid['total_raid'],
            'complaints' => count($complaints),
            'verifications' => count($verifications),
        ];

        $totalRecovered = collect($enquiriesRegistered)
            ->pluck('amount_recovered')
            ->filter()
            ->map(fn ($v) => $this->stats->parseAmount($v))
            ->filter()
            ->sum();

        return [
            'unit_name' => $unit,
            'report_date' => $day,
            'highlights' => $highlights,
            'payload' => [
                'complaints_registered' => $complaints,
                'verifications_registered' => $verifications,
                'enquiries_registered' => $enquiriesRegistered,
                'enquiries_closed' => $enquiriesClosed,
                'cases_registered' => $casesRegistered,
                'cases_disposed' => $casesDisposed,
                'arrests' => $arrests,
                'lockup_list' => $this->lockupList($circleId, $date),
                'bail_records' => $this->bailRecords($circleId, $day),
                'po_ca_summary' => ['pos' => $poStats, 'cas' => $caStats],
                'raid' => $raid,
                'progress_cases' => $this->progressCases($circleId, $date),
                'progress_enquiries' => $this->progressEnquiries($circleId, $date),
                'progress_cases_official' => $this->progressCasesOfficial($circleId, $date),
                'progress_enquiries_official' => $this->progressEnquiriesOfficial($circleId, $date),
                'summary' => [
                    'total_complaints_registered' => count($complaints),
                    'total_verifications_registered' => count($verifications),
                    'total_cases_registered' => count($casesRegistered),
                    'total_enquiries_registered' => count($enquiriesRegistered),
                    'total_arrests' => count($arrests),
                    'accused_in_lockup' => $highlights['accused_in_lockup'],
                    'arrested_pos' => $poStats['arrested'] ?? 0,
                    'arrested_cas' => $caStats['arrested'] ?? 0,
                    'total_amount_recovered' => $totalRecovered > 0 ? $this->stats->formatAmount($totalRecovered) : null,
                ],
            ],
        ];
    }

    private function enquiryBase(int $circleId)
    {
        return Enquiry::query()
            ->with(['complaint.circle', 'officer:id,name'])
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId));
    }

    private function enquiriesRegistered(int $circleId, string $day): array
    {
        $rows = $this->enquiryBase($circleId)
            ->where(function ($q) use ($day) {
                $q->whereDate('reg_date', $day)
                    ->orWhere(function ($qq) use ($day) {
                        $qq->whereNull('reg_date')->whereDate('created_at', $day);
                    });
            })
            ->latest('id')
            ->limit(200)
            ->get();

        $recoveryMap = $this->stats->recoveryAmountsForEnquiries($rows->pluck('id'));

        return $rows
            ->map(function (Enquiry $e) use ($recoveryMap) {
                $row = $this->mapEnquiryRow($e);
                if (isset($recoveryMap[$e->id])) {
                    $row['amount_recovered'] = $this->stats->formatAmount($recoveryMap[$e->id]);
                }

                return $row;
            })
            ->values()
            ->all();
    }

    private function enquiriesClosed(int $circleId, string $day): array
    {
        return $this->enquiryBase($circleId)
            ->whereIn('status', ['closed', 'approved', 'converted_to_case'])
            ->where(function ($q) use ($day) {
                $q->whereDate('approved_at', $day)
                    ->orWhereDate('updated_at', $day);
            })
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Enquiry $e) => array_merge($this->mapEnquiryRow($e), [
                'closure_reason' => $e->closure_reason,
            ]))
            ->values()
            ->all();
    }

    private function mapEnquiryRow(Enquiry $e): array
    {
        return [
            'enquiry_number' => $e->enquiry_number,
            'dated' => optional($e->reg_date ?? $e->created_at)->format('d.m.Y'),
            'complainant' => $e->complaint?->complainant_name,
            'circle' => $e->complaint?->circle?->name,
            'eo_name' => $e->officer?->name,
            'gist' => $e->cfr_summary ?: $e->complaint?->description,
            'accused' => $e->charge_against,
            'amount_involved' => $e->complaint?->amount_involved,
            'amount_recovered' => null,
            'cms_entered' => $e->enquiry_number ? 'Yes' : 'No',
        ];
    }

    private function casesRegistered(int $circleId, string $day): array
    {
        return CaseFile::query()
            ->with(['enquiry.complaint.circle', 'investigationOfficer:id,name', 'arrests'])
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereDate('created_at', $day)
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (CaseFile $c) => array_merge($this->caseAccusedCounts($c), [
                'enquiry_no' => $c->enquiry?->enquiry_number,
                'fir_no' => $c->fir_no,
                'fir_no_dated_sections' => trim(($c->fir_no ?? '') . ' / ' . optional($c->created_at)->format('d.m.Y') . ' / ' . ($c->enquiry?->complaint?->laws ?? '')),
                'dated' => optional($c->created_at)->format('d.m.Y'),
                'sections' => $c->enquiry?->complaint?->laws,
                'circle' => $c->enquiry?->complaint?->circle?->name,
                'io_name' => $c->investigationOfficer?->name,
                'gist' => $c->enquiry?->cfr_summary ?: $c->enquiry?->complaint?->description,
                'accused' => $c->enquiry?->charge_against,
                'amount_involved' => $c->enquiry?->complaint?->amount_involved,
                'amount_recovered' => null,
                'cms_entered' => $c->fir_no ? 'Yes' : 'No',
            ]))
            ->values()
            ->all();
    }

    private function casesDisposed(int $circleId, string $day): array
    {
        return CaseFile::query()
            ->with(['enquiry.complaint.circle', 'investigationOfficer:id,name', 'arrests'])
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->where(function ($q) {
                $q->where('status', 'closed')
                    ->orWhere('status', 'like', 'closed_%')
                    ->orWhereIn('status', ['challan_submitted', 'transferred']);
            })
            ->whereDate('updated_at', $day)
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (CaseFile $c) => array_merge($this->caseAccusedCounts($c), [
                'fir_no' => $c->fir_no,
                'fir_no_dated_sections' => trim(($c->fir_no ?? '') . ' / ' . optional($c->updated_at)->format('d.m.Y') . ' / ' . ($c->enquiry?->complaint?->laws ?? '')),
                'dated' => optional($c->updated_at)->format('d.m.Y'),
                'sections' => $c->enquiry?->complaint?->laws,
                'circle' => $c->enquiry?->complaint?->circle?->name,
                'io_name' => $c->investigationOfficer?->name,
                'gist' => $c->enquiry?->cfr_summary ?: $c->enquiry?->complaint?->description,
                'accused' => $c->enquiry?->charge_against,
                'amount_involved' => $c->enquiry?->complaint?->amount_involved,
                'reason' => $this->disposalReason($c),
                'disposal_detail' => ucfirst($this->disposalReason($c)),
            ]))
            ->values()
            ->all();
    }

    private function disposalReason(CaseFile $case): string
    {
        if ($case->recommendation === 'challan_submission') {
            return 'challan';
        }
        if ($case->recommendation === 'transfer' || $case->status === 'transferred') {
            return 'transferred';
        }
        if ($case->recommendation === 'merge') {
            return 'merged';
        }
        if (str_contains((string) $case->status, 'discharge')) {
            return 'discharge';
        }
        if (str_contains((string) $case->status, 'conviction')) {
            return 'conviction';
        }
        if (str_contains((string) $case->status, 'acquittal')) {
            return 'acquittal';
        }

        return $case->recommendation ?: 'closed';
    }

    private function arrestsToday(int $circleId, string $day): array
    {
        $flagged = $this->stats->poCaAccusedQuery($circleId)->get(['cnic', 'name', 'description']);

        return Arrest::query()
            ->with(['caseFile.enquiry.complaint'])
            ->whereDate('arrest_date', $day)
            ->whereHas('caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(function (Arrest $a) use ($flagged) {
                $isPoCa = $this->stats->isPoCaArrest($a, $flagged);

                return [
                    'circle' => $a->caseFile?->enquiry?->complaint?->circle?->name ?? 'NCCIA',
                    'accused_name' => $a->accused_name . ($isPoCa ? ' (PO/CA)' : ''),
                    'accused_so' => $a->accused_name,
                    'cnic' => $a->cnic,
                    'phone' => $a->caseFile?->enquiry?->complaint?->contact_no,
                    'fir_no' => $a->caseFile?->fir_no,
                    'fir_no_date' => trim(($a->caseFile?->fir_no ?? '') . ' & ' . optional($a->caseFile?->created_at)->format('d.m.Y')),
                    'fir_date' => optional($a->caseFile?->created_at)->format('d.m.Y'),
                    'sections' => $a->caseFile?->enquiry?->complaint?->laws,
                    'in_lockup' => $a->remand_details && !preg_match('/bail/i', (string) $a->remand_details) ? 'Yes' : 'No',
                    'arrest_date' => optional($a->arrest_date)->format('d.m.Y'),
                ];
            })
            ->values()
            ->all();
    }

    private function complaintsRegistered(int $circleId, string $day): array
    {
        return Complaint::query()
            ->where('circle_id', $circleId)
            ->where(function ($q) use ($day) {
                $q->whereDate('report_date', $day)
                    ->orWhereDate('created_at', $day);
            })
            ->latest('id')
            ->limit(200)
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
                'received_from' => $c->received_from,
                'priority_type' => $c->priority_type,
                'operator_name' => $c->operator_name,
                'scrutiny_result' => $c->scrutiny_result,
                'status' => $c->status,
                'final_status' => $c->final_status,
            ])
            ->values()
            ->all();
    }

    private function verificationsToday(int $circleId, string $day): array
    {
        return VerificationReport::query()
            ->with(['complaint.circle', 'creator:id,name'])
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->where(function ($q) use ($day) {
                $q->whereDate('verification_date', $day)
                    ->orWhereDate('created_at', $day);
            })
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (VerificationReport $v) => [
                'tracking_no' => $v->tracking_no,
                'dated' => optional($v->verification_date)->format('d.m.Y'),
                'complainant' => $v->complaint?->complainant_name,
                'circle' => $v->complaint?->circle?->name,
                'victim_name' => $v->victim_name,
                'victim_cnic' => $v->victim_cnic,
                'victim_phone' => $v->victim_phone,
                'city' => $v->city,
                'crime_category' => $v->crime_category,
                'accused_names' => $this->accusedNames($v->accused),
                'verifier' => $v->creator?->name,
                'recommendation' => $v->recommendation,
                'inquiry_no' => $v->inquiry_no,
                'case_no' => $v->case_no,
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

    private function accusedNames($accused): string
    {
        if (!is_array($accused)) {
            return '';
        }

        return collect($accused)
            ->map(fn ($a) => is_array($a) ? (string) ($a['name'] ?? '') : (string) $a)
            ->filter()
            ->implode(', ');
    }

    private function progressCases(int $circleId, Carbon $date): array
    {
        $year = (int) $date->year;
        $pending = CaseFile::query()
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereNotIn('status', ['closed'])
            ->count();

        return [[
            'unit' => 'CCRC',
            'previous' => CaseFile::query()
                ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
                ->whereYear('created_at', '<', $year)
                ->count(),
            'added' => CaseFile::query()
                ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
                ->whereYear('created_at', $year)
                ->count(),
            'pending' => $pending,
            'as_on' => $date->format('d.m.Y'),
        ]];
    }

    private function progressEnquiries(int $circleId, Carbon $date): array
    {
        $year = (int) $date->year;
        $pending = Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereNotIn('status', ['closed', 'approved'])
            ->count();

        return [[
            'unit' => 'CCRC',
            'previous' => Enquiry::query()
                ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
                ->whereYear('created_at', '<', $year)
                ->count(),
            'added' => Enquiry::query()
                ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
                ->whereYear('created_at', $year)
                ->count(),
            'pending' => $pending,
            'as_on' => $date->format('d.m.Y'),
        ]];
    }

    private function caseAccusedCounts(CaseFile $case): array
    {
        $case->loadMissing('arrests');
        $total = max(1, $case->arrests->count() ?: 1);
        $arrested = $case->arrests->count();
        $onBail = $case->arrests->filter(fn (Arrest $a) => $a->remand_details && preg_match('/bail/i', (string) $a->remand_details))->count();

        return [
            'total_accused' => $total,
            'accused_arrested' => $arrested,
            'accused_not_arrested' => max(0, $total - $arrested),
            'accused_on_bail' => $onBail,
        ];
    }

    private function lockupList(int $circleId, Carbon $date): array
    {
        $day = $date->toDateString();

        return Arrest::query()
            ->with(['caseFile.enquiry.complaint'])
            ->whereDate('arrest_date', '<=', $day)
            ->whereHas('caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereHas('caseFile', fn ($q) => $q->where('status', '!=', 'closed'))
            ->latest('arrest_date')
            ->limit(100)
            ->get()
            ->filter(fn (Arrest $a) => !$a->remand_details || !preg_match('/bail/i', (string) $a->remand_details))
            ->map(fn (Arrest $a) => [
                'circle' => $a->caseFile?->enquiry?->complaint?->circle?->name ?? 'NCCIA',
                'accused_so' => $a->accused_name,
                'cnic' => $a->cnic,
                'phone' => $a->caseFile?->enquiry?->complaint?->contact_no,
                'fir_no_date' => trim(($a->caseFile?->fir_no ?? '') . ' & ' . optional($a->caseFile?->created_at)->format('d.m.Y')),
                'sections' => $a->caseFile?->enquiry?->complaint?->laws,
                'in_lockup' => 'Yes',
            ])
            ->values()
            ->all();
    }

    private function bailRecords(int $circleId, string $day): array
    {
        return Arrest::query()
            ->with(['caseFile.enquiry.complaint'])
            ->whereHas('caseFile.enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->where(function ($q) use ($day) {
                $q->whereDate('arrest_date', $day)->orWhereDate('updated_at', $day);
            })
            ->get()
            ->filter(fn (Arrest $a) => $a->remand_details && preg_match('/bail/i', (string) $a->remand_details))
            ->map(fn (Arrest $a, $i) => [
                'sr' => $i + 1,
                'police_station' => 'NCCIA CCRC ' . ($a->caseFile?->enquiry?->complaint?->circle?->name ?? 'Lahore'),
                'accused_name' => $a->accused_name,
                'fir_no' => $a->caseFile?->fir_no,
                'court' => $a->remand_details,
                'bail_status' => $a->remand_details,
            ])
            ->values()
            ->all();
    }

    private function progressCasesOfficial(int $circleId, Carbon $date): array
    {
        $year = (int) $date->year;
        $prevYear = $year - 1;

        $pending = CaseFile::query()
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereNotIn('status', ['closed'])
            ->count();

        $disposed = CaseFile::query()
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->where(function ($q) {
                $q->where('status', 'closed')->orWhere('status', 'like', 'closed_%');
            })
            ->whereYear('updated_at', $year)
            ->count();

        $addedYear = CaseFile::query()
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereYear('created_at', $year)
            ->count();

        $addedPrev = CaseFile::query()
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereYear('created_at', $prevYear)
            ->count();

        $previous = CaseFile::query()
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereYear('created_at', '<', $prevYear)
            ->count();

        $lessThan6 = CaseFile::query()
            ->whereHas('enquiry.complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereNotIn('status', ['closed'])
            ->whereDate('created_at', '>=', $date->copy()->subMonths(6)->toDateString())
            ->count();

        $moreThan6 = max(0, $pending - $lessThan6);

        return [[
            'unit' => 'CCRC-Lahore',
            'previous' => $previous,
            'added_prev' => $addedPrev,
            'added_curr' => $addedYear,
            'total' => $previous + $addedPrev + $addedYear,
            'disposed' => $disposed,
            'pending' => $pending,
            'less_than_6' => $lessThan6,
            'more_than_6' => $moreThan6,
            'balance' => $pending,
        ]];
    }

    private function progressEnquiriesOfficial(int $circleId, Carbon $date): array
    {
        $year = (int) $date->year;
        $prevYear = $year - 1;

        $pending = Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereNotIn('status', ['closed', 'approved', 'converted_to_case'])
            ->count();

        $disposed = Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereIn('status', ['closed', 'approved', 'converted_to_case'])
            ->whereYear('updated_at', $year)
            ->count();

        $addedYear = Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereYear('created_at', $year)
            ->count();

        $addedPrev = Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereYear('created_at', $prevYear)
            ->count();

        $previous = Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereYear('created_at', '<', $prevYear)
            ->count();

        $lessThan3 = Enquiry::query()
            ->whereHas('complaint', fn ($q) => $q->where('circle_id', $circleId))
            ->whereNotIn('status', ['closed', 'approved', 'converted_to_case'])
            ->whereDate('created_at', '>=', $date->copy()->subMonths(3)->toDateString())
            ->count();

        $moreThan3 = max(0, $pending - $lessThan3);

        return [[
            'unit' => 'CCRC-Lahore',
            'previous' => $previous,
            'added_prev' => $addedPrev,
            'added_curr' => $addedYear,
            'total' => $previous + $addedPrev + $addedYear,
            'disposed' => $disposed,
            'pending' => $pending,
            'less_than_3' => $lessThan3,
            'more_than_3' => $moreThan3,
            'balance' => $pending,
        ]];
    }
}

<?php

namespace App\Services;

use App\Models\Arrest;
use App\Models\CaseActivity;
use App\Models\CaseFile;
use App\Models\Enquiry;
use App\Models\EnquiryAccused;
use App\Models\EnquiryActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AdminReportStatsService
{
    /** @var list<string> */
    private const RECOVERY_ACTIVITY_TYPES = ['recovery', 'recoveries'];

    /** @var list<string> */
    private const RAID_ACTIVITY_TYPES = ['raid'];

    public function accusedInLockup(int $circleId, ?Carbon $asOf = null): int
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $day = $asOf->toDateString();
        $cutoff = $asOf->copy()->subDays(15)->toDateString();

        return $this->circleArrestsQuery($circleId)
            ->whereDate('arrest_date', '<=', $day)
            ->whereHas('caseFile', function (Builder $q) use ($day) {
                $q->where(function (Builder $qq) use ($day) {
                    $qq->where('status', '!=', 'closed')
                        ->orWhereDate('updated_at', '>', $day);
                });
            })
            ->where(function (Builder $q) use ($cutoff, $day) {
                $q->where(function (Builder $qq) use ($cutoff, $day) {
                    $qq->where(function (Builder $inner) {
                        $inner->whereNull('remand_details')
                            ->orWhere('remand_details', '');
                    })->whereDate('arrest_date', '>=', $cutoff)
                        ->whereDate('arrest_date', '<=', $day);
                })->orWhere(function (Builder $qq) {
                    foreach (['lockup', 'lock up', 'remand', 'custody', 'judicial', 'physical'] as $term) {
                        $qq->orWhere('remand_details', 'like', '%' . $term . '%');
                    }
                });
            })
            ->get(['id', 'remand_details', 'arrest_date'])
            ->reject(fn (Arrest $a) => $this->isBailRemand($a->remand_details))
            ->count();
    }

    public function bailCountOnDate(int $circleId, string $day): int
    {
        return $this->circleArrestsQuery($circleId)
            ->where(function (Builder $q) use ($day) {
                $q->whereDate('arrest_date', $day)
                    ->orWhereDate('updated_at', $day);
            })
            ->get(['id', 'remand_details'])
            ->filter(fn (Arrest $a) => $this->isBailRemand($a->remand_details))
            ->count();
    }

    public function oldAccusedArrestedOnDate(int $circleId, string $day): int
    {
        $year = (int) Carbon::parse($day)->year;

        return $this->circleArrestsQuery($circleId)
            ->whereDate('arrest_date', $day)
            ->with('caseFile:id,created_at')
            ->get(['id', 'cnic', 'case_id', 'arrest_date'])
            ->filter(function (Arrest $a) use ($day, $year) {
                if ($a->cnic) {
                    $prior = Arrest::query()
                        ->where('cnic', $a->cnic)
                        ->whereKeyNot($a->id)
                        ->whereDate('arrest_date', '<', $day)
                        ->exists();
                    if ($prior) {
                        return true;
                    }
                }

                return $a->caseFile && (int) $a->caseFile->created_at->year < $year;
            })
            ->count();
    }

    public function recoveryAmountForEnquiry(int $enquiryId, ?int $caseId = null): ?float
    {
        $total = 0.0;
        $found = false;

        $enquiryActivities = EnquiryActivity::query()
            ->where('enquiry_id', $enquiryId)
            ->whereIn('type', self::RECOVERY_ACTIVITY_TYPES)
            ->get(['description', 'meta']);

        foreach ($enquiryActivities as $activity) {
            $amount = $this->amountFromActivity($activity->description, $activity->meta);
            if ($amount !== null) {
                $total += $amount;
                $found = true;
            }
        }

        if ($caseId) {
            $caseActivities = CaseActivity::query()
                ->where('case_id', $caseId)
                ->whereIn('type', self::RECOVERY_ACTIVITY_TYPES)
                ->get(['description', 'meta']);

            foreach ($caseActivities as $activity) {
                $amount = $this->amountFromActivity($activity->description, $activity->meta);
                if ($amount !== null) {
                    $total += $amount;
                    $found = true;
                }
            }
        }

        return $found ? $total : null;
    }

    /**
     * @param  iterable<int>  $enquiryIds
     * @return array<int, float>
     */
    public function recoveryAmountsForEnquiries(iterable $enquiryIds, array $enquiryToCase = []): array
    {
        $ids = collect($enquiryIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $amounts = array_fill_keys($ids->all(), 0.0);
        $found = [];

        EnquiryActivity::query()
            ->whereIn('enquiry_id', $ids)
            ->whereIn('type', self::RECOVERY_ACTIVITY_TYPES)
            ->get(['enquiry_id', 'description', 'meta'])
            ->each(function (EnquiryActivity $activity) use (&$amounts, &$found) {
                $amount = $this->amountFromActivity($activity->description, $activity->meta);
                if ($amount === null) {
                    return;
                }
                $amounts[$activity->enquiry_id] = ($amounts[$activity->enquiry_id] ?? 0) + $amount;
                $found[$activity->enquiry_id] = true;
            });

        $caseIds = array_values(array_filter($enquiryToCase));
        if ($caseIds !== []) {
            CaseActivity::query()
                ->whereIn('case_id', $caseIds)
                ->whereIn('type', self::RECOVERY_ACTIVITY_TYPES)
                ->get(['case_id', 'description', 'meta'])
                ->each(function (CaseActivity $activity) use (&$amounts, &$found, $enquiryToCase) {
                    $enquiryId = array_search($activity->case_id, $enquiryToCase, true);
                    if ($enquiryId === false) {
                        return;
                    }
                    $amount = $this->amountFromActivity($activity->description, $activity->meta);
                    if ($amount === null) {
                        return;
                    }
                    $amounts[$enquiryId] = ($amounts[$enquiryId] ?? 0) + $amount;
                    $found[$enquiryId] = true;
                });
        }

        return collect($amounts)
            ->filter(fn ($_, $id) => isset($found[$id]))
            ->all();
    }

    public function raidStatsForRange(int $circleId, Carbon $start, Carbon $end): array
    {
        $enquiryRaids = EnquiryActivity::query()
            ->whereIn('type', self::RAID_ACTIVITY_TYPES)
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->count();

        $caseRaids = CaseActivity::query()
            ->whereIn('type', self::RAID_ACTIVITY_TYPES)
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('caseFile.enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->count();

        $enquiriesAfterRaid = Enquiry::query()
            ->whereHas('complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->whereHas('activities', function (Builder $q) use ($start, $end) {
                $q->whereIn('type', self::RAID_ACTIVITY_TYPES)
                    ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->count();

        $casesAfterRaid = CaseFile::query()
            ->whereHas('enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('enquiry.activities', function (Builder $q) {
                $q->whereIn('type', self::RAID_ACTIVITY_TYPES);
            })
            ->count();

        return [
            'total_raid' => $enquiryRaids + $caseRaids,
            'cases_after_raid' => $casesAfterRaid,
            'enquiries_after_raid' => $enquiriesAfterRaid,
        ];
    }

    public function poStatsForRange(int $circleId, Carbon $start, Carbon $end): array
    {
        return $this->personStatusStatsForRange($circleId, $start, $end, 'po');
    }

    public function caStatsForRange(int $circleId, Carbon $start, Carbon $end): array
    {
        return $this->personStatusStatsForRange($circleId, $start, $end, 'ca');
    }

    private function personStatusStatsForRange(int $circleId, Carbon $start, Carbon $end, string $kind): array
    {
        $flagged = $this->poCaAccusedQuery($circleId)
            ->get(['id', 'enquiry_id', 'cnic', 'name', 'description', 'created_at'])
            ->filter(fn (EnquiryAccused $a) => $this->matchesPersonStatus($a->description, $kind));

        $previous = $flagged
            ->filter(fn (EnquiryAccused $a) => $a->created_at && $a->created_at->lt($start))
            ->count();

        $added = $flagged
            ->filter(fn (EnquiryAccused $a) => $a->created_at && $a->created_at->between($start, $end))
            ->count();

        $added += EnquiryActivity::query()
            ->where('type', 'arrest_warrant')
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->get(['description', 'meta'])
            ->filter(fn (EnquiryActivity $a) => $this->matchesPersonStatus($a->description, $kind)
                || $this->matchesPersonStatus(json_encode($a->meta), $kind))
            ->count();

        $added += CaseActivity::query()
            ->where('type', 'arrest_warrant')
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('caseFile.enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->get(['description', 'meta'])
            ->filter(fn (CaseActivity $a) => $this->matchesPersonStatus($a->description, $kind)
                || $this->matchesPersonStatus(json_encode($a->meta), $kind))
            ->count();

        $arrested = $this->circleArrestsQuery($circleId)
            ->whereBetween('arrest_date', [$start->toDateString(), $end->toDateString()])
            ->get(['cnic', 'accused_name', 'remand_details'])
            ->filter(function (Arrest $a) use ($flagged, $kind) {
                if ($this->matchesPersonStatus($a->remand_details, $kind)) {
                    return true;
                }

                return $flagged->contains(function (EnquiryAccused $accused) use ($a) {
                    if ($a->cnic && $accused->cnic && $a->cnic === $accused->cnic) {
                        return true;
                    }

                    return $a->accused_name
                        && $accused->name
                        && strcasecmp(trim($a->accused_name), trim($accused->name)) === 0;
                });
            })
            ->count();

        return [
            'previous' => $previous,
            'added' => $added,
            'arrested' => $arrested,
            'pending' => max(0, $previous + $added - $arrested),
        ];
    }

    public function poCaStatsForRange(int $circleId, Carbon $start, Carbon $end): array
    {
        $flagged = $this->poCaAccusedQuery($circleId)->get(['id', 'enquiry_id', 'cnic', 'name', 'description', 'created_at']);

        $previous = $flagged
            ->filter(fn (EnquiryAccused $a) => $a->created_at && $a->created_at->lt($start))
            ->count();

        $added = $flagged
            ->filter(fn (EnquiryAccused $a) => $a->created_at && $a->created_at->between($start, $end))
            ->count();

        $added += EnquiryActivity::query()
            ->where('type', 'arrest_warrant')
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->get(['description', 'meta'])
            ->filter(fn (EnquiryActivity $a) => $this->isPoCaText($a->description) || $this->isPoCaText(json_encode($a->meta)))
            ->count();

        $added += CaseActivity::query()
            ->where('type', 'arrest_warrant')
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('caseFile.enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->get(['description', 'meta'])
            ->filter(fn (CaseActivity $a) => $this->isPoCaText($a->description) || $this->isPoCaText(json_encode($a->meta)))
            ->count();

        $arrested = $this->circleArrestsQuery($circleId)
            ->whereBetween('arrest_date', [$start->toDateString(), $end->toDateString()])
            ->get(['cnic', 'accused_name', 'remand_details'])
            ->filter(function (Arrest $a) use ($flagged) {
                if ($this->isPoCaText($a->remand_details)) {
                    return true;
                }

                return $flagged->contains(function (EnquiryAccused $accused) use ($a) {
                    if ($a->cnic && $accused->cnic && $a->cnic === $accused->cnic) {
                        return true;
                    }

                    return $a->accused_name
                        && $accused->name
                        && strcasecmp(trim($a->accused_name), trim($accused->name)) === 0;
                });
            })
            ->count();

        return [
            'previous' => $previous,
            'added' => $added,
            'arrested' => $arrested,
            'pending' => max(0, $previous + $added - $arrested),
        ];
    }

    public function caseDisposalsForRange(int $circleId, Carbon $start, Carbon $end): array
    {
        $cases = CaseFile::query()
            ->whereHas('enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->whereBetween('updated_at', [$start, $end])
            ->get(['id', 'status', 'recommendation', 'updated_at']);

        $interimChallan = $cases->where('status', 'challan_submitted')->count();

        $finalChallan = $cases
            ->where('status', 'closed')
            ->where('recommendation', 'challan_submission')
            ->count();

        $discharge = $cases
            ->filter(fn (CaseFile $c) => $c->status === 'closed_discharge' || str_starts_with((string) $c->status, 'closed_discharge'))
            ->count();

        $transferred = $cases
            ->filter(fn (CaseFile $c) => $c->recommendation === 'transfer' || $c->status === 'transferred')
            ->count();

        $merged = $cases->where('recommendation', 'merge')->count();

        return [
            'interim_challan' => $interimChallan,
            'final_challan' => $finalChallan,
            'discharge' => $discharge,
            'transferred' => $transferred,
            'merged' => $merged,
        ];
    }

    public function formatAmount(?float $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        return 'PKR ' . number_format($amount, 0, '.', ',');
    }

    public function isPoCaArrest(Arrest $arrest, ?Collection $flaggedAccused = null): bool
    {
        if ($this->isPoCaText($arrest->remand_details)) {
            return true;
        }

        if ($flaggedAccused === null) {
            $circleId = (int) ($arrest->caseFile?->enquiry?->complaint?->circle_id ?? 0);
            $flaggedAccused = $circleId > 0
                ? $this->poCaAccusedQuery($circleId)->get(['cnic', 'name', 'description'])
                : collect();
        }

        if ($flaggedAccused->isEmpty()) {
            return false;
        }

        return $flaggedAccused->contains(function (EnquiryAccused $accused) use ($arrest) {
            if ($arrest->cnic && $accused->cnic && $arrest->cnic === $accused->cnic) {
                return true;
            }

            return $arrest->accused_name
                && $accused->name
                && strcasecmp(trim($arrest->accused_name), trim($accused->name)) === 0;
        });
    }

    private function circleArrestsQuery(int $circleId): Builder
    {
        return Arrest::query()
            ->whereHas('caseFile.enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId));
    }

    public function poCaAccusedQuery(int $circleId): Builder
    {
        if (!Schema::hasTable('enquiry_accused')) {
            return EnquiryAccused::query()->whereRaw('0 = 1');
        }

        return EnquiryAccused::query()
            ->whereHas('enquiry.complaint', fn (Builder $q) => $q->where('circle_id', $circleId))
            ->where(function (Builder $q) {
                foreach (['proclaimed', 'absconder', 'p.o', 'p. o', 'court absconder', ' c.a', 'c.a.'] as $term) {
                    $q->orWhere('description', 'like', '%' . $term . '%');
                }
            });
    }

    private function isBailRemand(?string $remand): bool
    {
        if (!$remand) {
            return false;
        }

        return (bool) preg_match('/\b(bail|b\.?\s*a\.?\s*l|released on bail|post[\s-]?arrest bail|pre[\s-]?arrest bail)\b/i', $remand);
    }

    private function isPoCaText(?string $text): bool
    {
        if (!$text) {
            return false;
        }

        return $this->matchesPersonStatus($text, 'po') || $this->matchesPersonStatus($text, 'ca');
    }

    private function matchesPersonStatus(?string $text, string $kind): bool
    {
        if (!$text) {
            return false;
        }

        if ($kind === 'ca') {
            return (bool) preg_match('/\b(C\.?\s*A\.?|court\s+absconder)\b/i', $text);
        }

        return (bool) preg_match('/\b(P\.?\s*O\.?|proclaimed\s+offender|absconder)\b/i', $text);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function amountFromActivity(?string $description, ?array $meta): ?float
    {
        foreach ([
            $description,
            is_array($meta) ? ($meta['recovered_amount'] ?? null) : null,
            is_array($meta) ? ($meta['amount'] ?? null) : null,
            is_array($meta) ? ($meta['amount_recovered'] ?? null) : null,
        ] as $candidate) {
            $parsed = $this->parseAmount($candidate);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    public function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = (string) $value;
        if (preg_match('/(?:PKR|Rs\.?|\/-)\s*([\d,]+(?:\.\d+)?)/i', $text, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }

        if (preg_match('/([\d,]+(?:\.\d+)?)\s*(?:PKR|Rs\.?|\/-)/i', $text, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }

        if (preg_match('/\b([\d,]+(?:\.\d+)?)\b/', $text, $matches)) {
            $number = (float) str_replace(',', '', $matches[1]);
            return $number > 0 ? $number : null;
        }

        return null;
    }
}

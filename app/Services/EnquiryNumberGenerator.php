<?php

namespace App\Services;

use App\Models\Enquiry;
use Illuminate\Support\Facades\DB;

class EnquiryNumberGenerator
{
    public function generate(?string $circleCode = null): string
    {
        $year = now()->format('y');
        $code = $circleCode ?: 'CCW';

        return DB::transaction(function () use ($code, $year) {
            $query = Enquiry::where('enquiry_number', 'like', "{$code}-E-%/{$year}");
            if (DB::transactionLevel() > 0) {
                $query->lockForUpdate();
            }

            $last = $query->orderByDesc('id')->value('enquiry_number');

            $seq = 1;
            if ($last && preg_match('/-E-(\d+)\//', $last, $m)) {
                $seq = ((int) $m[1]) + 1;
            }

            return "{$code}-E-{$seq}/{$year}";
        });
    }
}

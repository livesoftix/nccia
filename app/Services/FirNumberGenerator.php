<?php

namespace App\Services;

use App\Models\CaseFile;
use Illuminate\Support\Facades\DB;

class FirNumberGenerator
{
    public function generate(?string $circleCode = null): string
    {
        $year = now()->format('y');
        $code = $circleCode ?: 'CCW';

        return DB::transaction(function () use ($code, $year) {
            $query = CaseFile::where('fir_no', 'like', "{$code}-F-%/{$year}");
            if (DB::transactionLevel() > 0) {
                $query->lockForUpdate();
            }

            $last = $query
                ->orderByDesc('id')
                ->value('fir_no');

            $seq = 1;
            if ($last && preg_match('/-F-(\d+)\//', $last, $m)) {
                $seq = ((int) $m[1]) + 1;
            }

            return "{$code}-F-{$seq}/{$year}";
        });
    }
}

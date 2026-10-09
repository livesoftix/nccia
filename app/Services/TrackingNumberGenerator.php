<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;

class TrackingNumberGenerator
{
    public function generate(?Circle $circle = null): string
    {
        $year = now()->format('y');
        $maxSeq = DB::select("SELECT MAX(CAST(SUBSTRING_INDEX(tracking_no, '/', 1) AS UNSIGNED)) as max_seq FROM complaints WHERE tracking_no LIKE ?", ["%/{$year}"])[0]->max_seq ?? 0;
        $seq = str_pad(((int) $maxSeq) + 1, 4, '0', STR_PAD_LEFT);
        return "{$seq}/{$year}";
    }
}

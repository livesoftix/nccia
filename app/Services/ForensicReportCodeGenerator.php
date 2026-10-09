<?php

namespace App\Services;

use App\Models\ForensicRequest;
use Illuminate\Support\Facades\DB;

class ForensicReportCodeGenerator
{
    public function generateRequestNo(): string
    {
        $year = now()->format('y');

        return DB::transaction(function () use ($year) {
            $last = ForensicRequest::whereNotNull('request_no')
                ->where('request_no', 'like', "FR-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('request_no')
                ->value('request_no');

            $seq = 1;
            if ($last && preg_match('/FR-\d+-(\d+)/', $last, $m)) {
                $seq = ((int) $m[1]) + 1;
            }

            return sprintf('FR-%s-%04d', $year, $seq);
        });
    }

    /** Auto report code when FO opens the request. */
    public function generateReportCode(): string
    {
        $year = now()->format('y');

        return DB::transaction(function () use ($year) {
            $last = ForensicRequest::whereNotNull('report_code')
                ->where('report_code', 'like', "FREP-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('report_code')
                ->value('report_code');

            $seq = 1;
            if ($last && preg_match('/FREP-\d+-(\d+)/', $last, $m)) {
                $seq = ((int) $m[1]) + 1;
            }

            return sprintf('FREP-%s-%04d', $year, $seq);
        });
    }
}

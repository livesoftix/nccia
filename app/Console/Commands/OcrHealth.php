<?php

namespace App\Console\Commands;

use App\Models\ComplaintPdfImport;
use App\Services\Ocr\PythonOcrRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OcrHealth extends Command
{
    protected $signature = 'ocr:health {--json : Machine-readable output for monitoring}';

    protected $description = 'Check the Python OCR engine, languages, queue and import backlog';

    public function handle(PythonOcrRunner $runner): int
    {
        $report = ['engine' => null, 'ok' => false];
        try {
            $report['engine'] = $runner->health();
        } catch (\Throwable $e) {
            $report['engine'] = ['ok' => false, 'error' => $e->getMessage()];
        }
        $report['queue_connection'] = config('queue.default');
        $report['queue'] = config('ocr.queue');
        $report['pending_jobs'] = rescue(fn () => DB::table('jobs')->where('queue', config('ocr.queue'))->count(), null, false);
        $report['failed_jobs'] = rescue(fn () => DB::table('failed_jobs')->count(), null, false);
        $report['imports'] = ComplaintPdfImport::whereNotNull('circle_id')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $report['stalled'] = ComplaintPdfImport::whereNotNull('circle_id')
            ->whereIn('status', [ComplaintPdfImport::STATUS_QUEUED, ComplaintPdfImport::STATUS_PROCESSING])
            ->where('updated_at', '<', now()->subMinutes(30))->count();
        $report['ok'] = (bool) ($report['engine']['ok'] ?? false) && config('queue.default') !== 'sync';

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->info('OCR engine: ' . (($report['engine']['ok'] ?? false) ? 'OK' : 'NOT READY'));
            foreach (($report['engine'] ?? []) as $k => $v) {
                $this->line(sprintf('  %-22s %s', $k, is_array($v) ? implode(', ', $v) : var_export($v, true)));
            }
            $this->line('Queue: ' . $report['queue_connection'] . ' / ' . $report['queue'] . ' — pending ' . ($report['pending_jobs'] ?? '?') . ', failed ' . ($report['failed_jobs'] ?? '?'));
            if (config('queue.default') === 'sync') {
                $this->warn('QUEUE_CONNECTION=sync runs OCR inside the upload request. Use database or redis with a worker.');
            }
            $this->line('Imports: ' . json_encode($report['imports']) . ', stalled >30min: ' . $report['stalled']);
        }

        return $report['ok'] ? self::SUCCESS : self::FAILURE;
    }
}

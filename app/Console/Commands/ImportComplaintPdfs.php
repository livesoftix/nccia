<?php

namespace App\Console\Commands;

use App\Jobs\ProcessComplaintPdfImport;
use App\Models\ComplaintPdfImport;
use App\Models\User;
use App\Services\ComplaintPdfImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportComplaintPdfs extends Command
{
    protected $signature = 'complaints:import-pdfs
                            {path : Folder path or single PDF file}
                            {--user=1 : User ID to attribute imports to}
                            {--circle= : Force Circle ID or name for all files in this run}
                            {--io= : Force Enquiry Officer ID or name for all files in this run}
                            {--batch= : Optional batch id}
                            {--sync : Process immediately without queue}
                            {--chunk-size=50 : Batch size for database and queue dispatching}
                            {--extract-only : Extract only, do not create complaints}
                            {--in-place : Keep PDFs in place (do not copy into storage) — recommended for large batches}
                            {--copy : Copy PDFs into storage (default is in-place)}
                            {--no-attach : Do not copy PDF into public/uploads on apply}
                            {--skip-existing : Skip filenames already queued/imported (default true)}
                            {--no-skip-existing : Re-import even if filename exists}
                            {--limit=0 : Max PDFs this run (0 = all)}
                            {--dry-run : List matching PDFs only, do not import}';

    protected $description = 'Bulk import NCCIA verification PDFs (hierarchical: Circle -> IO -> Moharar). Use --in-place for 100k+ batches.';

    public function handle(ComplaintPdfImportService $service): int
    {
        $path = $this->argument('path');
        if (!file_exists($path)) {
            $this->error("Path not found: {$path}");

            return self::FAILURE;
        }

        $user = User::with('roles')->find((int) $this->option('user'));
        if (!$user) {
            $this->error('User not found. Pass --user=<id> of an operator/admin.');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $inPlace = !$this->option('copy');
        $skipExisting = !$this->option('no-skip-existing');
        $copyAttachment = !$this->option('no-attach') && !$this->option('extract-only');
        $autoApply = !$this->option('extract-only');
        $batchId = $this->option('batch') ?: (string) Str::uuid();
        $chunkSize = max(10, min(500, (int) $this->option('chunk-size', 50)));

        $this->info("Scanning PDFs under {$path}…");
        $queued = 0;
        $skipped = 0;
        $reused = 0;
        $seen = 0;
        $skippedNames = [];
        $chunk = [];

        foreach ($this->iteratePdfs($path) as $pdfPath) {
            if ($limit > 0 && $seen >= $limit) {
                break;
            }
            $seen++;
            $chunk[] = $pdfPath;

            if (count($chunk) >= $chunkSize) {
                [$q, $s, $r, $names] = $this->ingestChunk($service, $user, $batchId, $chunk, $inPlace, $skipExisting, $autoApply, $copyAttachment);
                $queued += $q;
                $skipped += $s;
                $reused += $r;
                $skippedNames = array_slice(array_merge($skippedNames, $names), 0, 15);
                $chunk = [];
                $this->line("  [Progress] Scanned: {$seen} | Queued/Processed: {$queued} | Skipped: {$skipped} | Reused: {$reused}");
                gc_collect_cycles();
            }
        }

        if ($chunk) {
            if ($this->option('dry-run')) {
                $this->line('Dry-run files:');
                foreach ($chunk as $p) {
                    $this->line('  ' . $p);
                }
            } else {
                [$q, $s, $r, $names] = $this->ingestChunk($service, $user, $batchId, $chunk, $inPlace, $skipExisting, $autoApply, $copyAttachment);
                $queued += $q;
                $skipped += $s;
                $reused += $r;
                $skippedNames = array_slice(array_merge($skippedNames, $names), 0, 15);
            }
        }

        $this->newLine();
        $this->info("Completed scanning {$seen} PDF(s) under {$path}");

        if ($this->option('dry-run')) {
            $this->info('Dry-run complete. No records created.');

            return self::SUCCESS;
        }

        if ($seen === 0) {
            $this->warn('Is folder mein koi .pdf file nahi mili. Path check karein (subfolders recursive hain).');

            return self::SUCCESS;
        }

        $this->info("Total Queued/Processed: {$queued} PDF(s). Reused failed: {$reused}. Skipped existing: {$skipped}. Batch ID: {$batchId}");
        foreach ($skippedNames as $name) {
            $this->line("  skip: {$name}");
        }

        if ($queued === 0 && $skipped > 0) {
            $this->warn('Sab files pehle se imported/pending hain. Failed dubara: php artisan complaints:retry-pdf-imports --sync');
            $this->warn('Ya force re-import: add --no-skip-existing');
        }

        if (!$this->option('sync')) {
            $this->line('Queue Workers start karne ke liye (several terminals):');
            $this->line('  php artisan queue:work --timeout=300 --tries=1 --sleep=1');
        }

        $counts = ComplaintPdfImport::query()
            ->where('batch_id', $batchId)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        if ($counts->isEmpty()) {
            $this->line('  (this batch: no new rows)');
        } else {
            $this->line('This batch summary:');
            foreach ($counts as $status => $count) {
                $this->line("  {$status}: {$count}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $paths
     * @return array{0: int, 1: int, 2: int, 3: list<string>}
     */
    private function ingestChunk(
        ComplaintPdfImportService $service,
        User $user,
        string $batchId,
        array $paths,
        bool $inPlace,
        bool $skipExisting,
        bool $autoApply,
        bool $copyAttachment,
    ): array {
        if ($this->option('dry-run')) {
            foreach ($paths as $p) {
                $this->line('  ' . $p);
            }

            return [0, 0, 0, []];
        }

        $stored = $service->registerLocalFiles($paths, $user, $batchId, $inPlace, $skipExisting);

        foreach ($stored['imports'] as $import) {
            if ($this->option('sync')) {
                $import = $service->process($import);
                if ($autoApply && $import->status === ComplaintPdfImport::STATUS_EXTRACTED) {
                    $import = $service->applyToSystem($import, $user, true, $copyAttachment);
                }
                $import = $import->fresh();
                if ($import->status === ComplaintPdfImport::STATUS_FAILED) {
                    $this->error("FAIL {$import->original_filename}: {$import->error_message}");
                } else {
                    $this->info("OK {$import->original_filename} → {$import->status}");
                }
            } else {
                ProcessComplaintPdfImport::dispatch($import->id, $user->id, $autoApply, $copyAttachment);
            }
        }

        return [
            count($stored['imports']),
            (int) ($stored['skipped'] ?? 0),
            (int) ($stored['reused'] ?? 0),
            $stored['skipped_names'] ?? [],
        ];
    }

    /**
     * @return \Generator<int, string>
     */
    private function iteratePdfs(string $path): \Generator
    {
        if (is_file($path)) {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') {
                yield $path;
            }

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                yield $file->getPathname();
            }
        }
    }
}

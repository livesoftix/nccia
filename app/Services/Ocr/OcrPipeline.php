<?php

namespace App\Services\Ocr;

use App\Jobs\ProcessOcrChunk;
use App\Models\Circle;
use App\Models\ComplaintPdfImport;
use App\Models\ComplaintPdfImportPage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OcrPipeline
{
    public function __construct(
        private PythonOcrRunner $runner,
        private OcrDecision $decision,
        private OcrRecordWriter $writer,
        private OcrFieldRules $rules,
    ) {
    }

    /**
     * Register an uploaded PDF in an authorized circle and queue it.
     *
     * @return array{import: ComplaintPdfImport, duplicate: bool}
     */
    public function register(UploadedFile $file, User $user, Circle $circle, ?string $batchId = null): array
    {
        if (!$user->canAccessCircle($circle->id)) {
            throw new AuthorizationException('You are not authorized to upload into this circle.');
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $existing = ComplaintPdfImport::where('circle_id', $circle->id)->where('file_hash', $hash)->first();
        if ($existing) {
            return ['import' => $existing, 'duplicate' => true];
        }

        // Private disk, partitioned by circle; never under public/.
        $path = sprintf('ocr/%d/%s/%s.pdf', $circle->id, now()->format('Y/m'), $hash);
        Storage::disk(config('ocr.disk'))->putFileAs(dirname($path), $file, basename($path));

        try {
            $import = ComplaintPdfImport::create([
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'batch_id' => $batchId ?: (string) Str::uuid(),
                'original_filename' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
                'stored_path' => $path,
                'file_hash' => $hash,
                'file_size' => $file->getSize(),
                'status' => ComplaintPdfImport::STATUS_QUEUED,
            ]);
        } catch (QueryException $e) {
            // Concurrent upload of the same file into the same circle: the unique index decides.
            $existing = ComplaintPdfImport::where('circle_id', $circle->id)->where('file_hash', $hash)->first();
            if ($existing) {
                return ['import' => $existing, 'duplicate' => true];
            }
            throw $e;
        }

        ProcessOcrChunk::dispatch($import->id);

        return ['import' => $import, 'duplicate' => false];
    }

    public function absolutePath(ComplaintPdfImport $import): string
    {
        return Storage::disk(config('ocr.disk'))->path($import->stored_path);
    }

    /**
     * Process the next unprocessed pages. Resumes from the checkpoint table, so a
     * retried or duplicated job never redoes finished pages or duplicates rows.
     *
     * @return bool true when more pages remain
     */
    public function processNextChunk(ComplaintPdfImport $import): bool
    {
        $path = $this->absolutePath($import);

        if (!$import->page_count) {
            $info = $this->runner->inspect($path);
            if (($info['sha256'] ?? null) !== $import->file_hash) {
                throw new OcrRejected('Stored file does not match the uploaded file hash.');
            }
            $import->update(['page_count' => (int) $info['page_count']]);
        }
        if ($import->status !== ComplaintPdfImport::STATUS_PROCESSING) {
            $import->update(['status' => ComplaintPdfImport::STATUS_PROCESSING]);
        }

        $done = ComplaintPdfImportPage::where('complaint_pdf_import_id', $import->id)->pluck('page_no')->all();
        $next = $this->firstMissing($done, (int) $import->page_count);
        if ($next === null) {
            return false;
        }

        $end = min((int) $import->page_count, $next + max(1, (int) config('ocr.chunk_pages')) - 1);
        $rows = [];
        foreach ($this->runner->pages($path, $next, $end) as $page) {
            $rows[] = [
                'complaint_pdf_import_id' => $import->id,
                'page_no' => (int) $page['page'],
                'method' => (string) ($page['method'] ?? 'error'),
                'text' => $page['text'] ?? null,
                'confidence' => round((float) ($page['confidence'] ?? 0), 4),
                'rotation' => (int) ($page['rotation'] ?? 0),
                'skew' => (float) ($page['skew'] ?? 0),
                'engine' => $page['engine'] ?? null,
                'error' => $page['error'] ?? null,
                'ms' => (int) ($page['ms'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        ComplaintPdfImportPage::upsert($rows, ['complaint_pdf_import_id', 'page_no'],
            ['method', 'text', 'confidence', 'rotation', 'skew', 'engine', 'error', 'ms', 'updated_at']);

        $count = ComplaintPdfImportPage::where('complaint_pdf_import_id', $import->id)->count();
        $import->update([
            'pages_done' => $count,
            'used_ocr' => ComplaintPdfImportPage::where('complaint_pdf_import_id', $import->id)->where('method', 'ocr')->exists(),
        ]);

        return $count < (int) $import->page_count;
    }

    /** Run field extraction over all stored pages and decide: auto-import or review. */
    public function finalize(ComplaintPdfImport $import): ComplaintPdfImport
    {
        $tmpDir = storage_path('app/private/ocr-tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0700, true);
        }
        $tmp = $tmpDir . DIRECTORY_SEPARATOR . $import->id . '-' . Str::random(16) . '.jsonl';
        try {
            $fh = fopen($tmp, 'wb');
            ComplaintPdfImportPage::where('complaint_pdf_import_id', $import->id)
                ->orderBy('page_no')
                ->select(['page_no', 'method', 'text', 'confidence', 'error'])
                ->chunk(50, function ($pages) use ($fh) {
                    foreach ($pages as $p) {
                        fwrite($fh, json_encode([
                            'page' => $p->page_no, 'method' => $p->method, 'text' => $p->text,
                            'confidence' => $p->confidence, 'error' => $p->error,
                        ], JSON_UNESCAPED_UNICODE) . "\n");
                    }
                });
            fclose($fh);
            $result = $this->runner->extract($tmp, $import->layout);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        $failedPages = ComplaintPdfImportPage::where('complaint_pdf_import_id', $import->id)->whereNotNull('error')->pluck('page_no')->all();
        $import->update([
            'layout' => $result['layout'] ?? null,
            'field_results' => $result['fields'] ?? [],
            'extracted_data' => ['accused' => $result['accused'] ?? [], 'warnings' => $result['warnings'] ?? [], 'failed_pages' => $failedPages],
            'circle_hint' => isset($result['circle_hint']) ? Str::limit((string) $result['circle_hint'], 115, '') : null,
            'mean_confidence' => $result['mean_confidence'] ?? null,
            'inquiry_ref' => $result['fields']['inquiry_no']['value'] ?? $import->inquiry_ref,
        ]);

        return $this->decide($import->fresh(), $failedPages);
    }

    private function decide(ComplaintPdfImport $import, array $failedPages = []): ComplaintPdfImport
    {
        $decision = $this->decision->evaluate($import, $import->field_results ?? []);
        $reasons = $decision['reasons'];
        if ($failedPages) {
            $reasons[] = 'pages could not be read: ' . implode(', ', array_slice($failedPages, 0, 20));
        }
        foreach (($import->extracted_data['warnings'] ?? []) as $warning) {
            $reasons[] = $warning;
        }
        $duplicates = $this->writer->possibleDuplicates($import, $decision['values']);
        if ($duplicates->isNotEmpty()) {
            $reasons[] = 'possible duplicate of complaint(s): ' . $duplicates->pluck('id')->implode(', ');
        }

        if ($decision['accept'] && $reasons === [] && $import->user) {
            return $this->writer->write($import, $decision['values'], $import->user);
        }

        $import->update([
            'status' => ComplaintPdfImport::STATUS_NEEDS_REVIEW,
            'review_reasons' => array_values(array_unique($reasons)),
            'processed_at' => now(),
        ]);

        return $import->fresh();
    }

    /**
     * Apply reviewer corrections (audited) and, when asked, finalise into the circle.
     *
     * @param  array<string, mixed>  $corrections  field => corrected value (null clears)
     */
    public function review(ComplaintPdfImport $import, User $reviewer, array $corrections, bool $approve, ?int $linkComplaintId = null): ComplaintPdfImport
    {
        if (!$reviewer->canAccessCircle($import->circle_id)) {
            throw new AuthorizationException('Not authorized for this circle.');
        }

        return DB::transaction(function () use ($import, $reviewer, $corrections, $approve, $linkComplaintId) {
            $import = ComplaintPdfImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($import->status === ComplaintPdfImport::STATUS_IMPORTED) {
                return $import;
            }

            $fields = $import->field_results ?? [];
            $changes = [];
            foreach ($corrections as $name => $value) {
                if (!array_key_exists($name, OcrFieldRules::TYPES)) {
                    throw new \InvalidArgumentException("Unknown field: {$name}");
                }
                if ($value !== null && $value !== '') {
                    [$ok, $normalized, $issue] = $this->rules->check($name, $value);
                    if (!$ok) {
                        throw new \InvalidArgumentException("{$name}: {$issue}");
                    }
                    $value = $normalized;
                }
                $old = $fields[$name]['value'] ?? null;
                if ($old === $value) {
                    continue;
                }
                $changes[$name] = ['old' => $old, 'new' => $value];
                $fields[$name] = array_merge($fields[$name] ?? ['name' => $name], [
                    'value' => $value, 'valid' => $value !== null, 'reviewed' => true, 'confidence' => 1.0,
                    'issues' => ['corrected by reviewer'],
                ]);
            }

            $import->update(['field_results' => $fields, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            if ($changes) {
                activity('ocr_review')->performedOn($import)->causedBy($reviewer)
                    ->withProperties(['changes' => $changes])->log('OCR fields corrected');
            }

            if (!$approve) {
                return $import->fresh();
            }

            $decision = $this->decision->evaluate($import->fresh(), $fields, true);
            if (!$decision['accept']) {
                throw new \InvalidArgumentException('Cannot approve: ' . implode('; ', $decision['reasons']));
            }
            activity('ocr_review')->performedOn($import)->causedBy($reviewer)
                ->withProperties(['link_complaint_id' => $linkComplaintId])->log('OCR import approved');

            return $this->writer->write($import, $decision['values'], $reviewer, $linkComplaintId);
        });
    }

    /** Re-queue a failed import. Finished pages are kept (checkpoint) and not re-run. */
    public function retry(ComplaintPdfImport $import): ComplaintPdfImport
    {
        if (!in_array($import->status, [ComplaintPdfImport::STATUS_FAILED, ComplaintPdfImport::STATUS_NEEDS_REVIEW], true)) {
            throw new \InvalidArgumentException('Only failed or review imports can be retried.');
        }
        $import->update(['status' => ComplaintPdfImport::STATUS_QUEUED, 'error_message' => null, 'review_reasons' => null]);
        ComplaintPdfImportPage::where('complaint_pdf_import_id', $import->id)->whereNotNull('error')->delete();
        ProcessOcrChunk::dispatch($import->id);

        return $import->fresh();
    }

    public function markFailed(ComplaintPdfImport $import, string $message): void
    {
        $import->update([
            'status' => ComplaintPdfImport::STATUS_FAILED,
            'error_message' => Str::limit($message, 1000),
            'processed_at' => now(),
        ]);
    }

    /** @param  array<int, int>  $done */
    private function firstMissing(array $done, int $pageCount): ?int
    {
        $set = array_flip($done);
        for ($page = 1; $page <= $pageCount; $page++) {
            if (!isset($set[$page])) {
                return $page;
            }
        }

        return null;
    }
}

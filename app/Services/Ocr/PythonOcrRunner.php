<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Process;

/**
 * Invokes the local Python OCR engine (python/nccia_ocr) as a child process.
 *
 * Arguments are passed as an array (no shell), the engine has no network service
 * and no database access, and its only output is JSON on stdout.
 */
class PythonOcrRunner
{
    public function health(): array
    {
        return $this->run(['health'], 60)[0] ?? [];
    }

    public function inspect(string $pdfPath): array
    {
        return $this->run(['inspect', $pdfPath], 300)[0] ?? [];
    }

    /** @return array<int, array<string, mixed>> one entry per page */
    public function pages(string $pdfPath, int $start, int $end): array
    {
        return $this->run(['pages', $pdfPath, '--start', (string) $start, '--end', (string) $end], (int) config('ocr.chunk_timeout'));
    }

    public function extract(string $pagesJsonlPath, ?string $layout = null): array
    {
        $args = ['extract', '--pages-file', $pagesJsonlPath];
        if ($layout) {
            $args[] = '--layout';
            $args[] = $layout;
        }

        return $this->run($args, 300)[0] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    private function run(array $args, int $timeout): array
    {
        $result = Process::path(config('ocr.engine_dir'))
            ->timeout($timeout)
            ->env($this->environment())
            ->run(array_merge([config('ocr.python'), '-m', 'nccia_ocr'], $args));

        $rows = [];
        foreach (preg_split('/\r?\n/', trim($result->output())) as $line) {
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('OCR engine returned non-JSON output.');
            }
            $rows[] = $decoded;
        }

        if ($result->exitCode() === 2) {
            throw new OcrRejected($rows[0]['error'] ?? 'PDF rejected by OCR engine.');
        }
        if (!$result->successful()) {
            $message = $rows[0]['error'] ?? trim($result->errorOutput()) ?: 'OCR engine failed.';
            throw new \RuntimeException('OCR engine error: ' . mb_substr($message, 0, 500));
        }

        return $rows;
    }

    private function environment(): array
    {
        return array_filter([
            'OCR_ENGINE' => config('ocr.engine'),
            'OCR_LANGUAGES' => config('ocr.languages'),
            'OCR_TESSERACT_CMD' => config('ocr.tesseract_cmd'),
            'OCR_DPI' => (string) config('ocr.dpi'),
            'OCR_MAX_PAGES' => (string) config('ocr.max_pages'),
            'OCR_MAX_FILE_MB' => (string) config('ocr.max_file_mb'),
            'OCR_MAX_OCR_PAGES' => (string) config('ocr.max_ocr_pages'),
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONDONTWRITEBYTECODE' => '1',
        ], fn ($v) => $v !== null && $v !== '');
    }
}

<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\File\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

class UploadScanner
{
    public function scan(File $file, string $field, ?float $remainingSeconds = null): void
    {
        if (!config('security.uploads.scan_required', app()->environment('production'))) {
            return;
        }
        $timeout = min(30, max(0.1, (float) config('security.uploads.scan_timeout_seconds', 10)));
        if ($remainingSeconds !== null) {
            if ($remainingSeconds <= 0) {
                throw new HttpException(503, 'Upload security scanning is temporarily unavailable. Try again later.');
            }
            $timeout = min($timeout, $remainingSeconds);
        }
        try {
            $process = $this->process($file->getPathname());
            // Never invoke a shell, return scanner output, or buffer unbounded output.
            $process->disableOutput()->setTimeout($timeout);
            $exit = $process->run();
        } catch (\Throwable $exception) {
            throw new HttpException(503, 'Upload security scanning is temporarily unavailable. Try again later.');
        }
        if ($exit === 1) {
            throw ValidationException::withMessages([$field => 'The file failed the malware security check.']);
        }
        if ($exit !== 0) {
            throw new HttpException(503, 'Upload security scanning is temporarily unavailable. Try again later.');
        }
    }

    public function scanStored(File $file): void
    {
        if (!config('security.uploads.scan_required', app()->environment('production'))) return;
        $hash = hash_file('sha256', $file->getPathname());
        if (!$hash) throw new HttpException(503, 'Upload security scanning is temporarily unavailable. Try again later.');
        $key = 'upload-scan:' . hash('sha256', config('security.uploads.scanner_binary', 'clamscan') . ':' . $hash);
        if (Cache::get($key) === true) return;
        // Covers legacy evidence as well as new uploads. Cache only clean results
        // briefly, keyed by exact contents so replacing a file invalidates the verdict.
        $this->scan($file, 'file');
        Cache::put($key, true, 300);
    }

    protected function process(string $path): Process
    {
        $binary = (new ExecutableFinder)->find((string) config('security.uploads.scanner_binary', 'clamscan'));
        if (!$binary) {
            throw new HttpException(503, 'Upload security scanning is temporarily unavailable. Try again later.');
        }
        return new Process([$binary,
            '--no-summary', '--stdout', '--infected', '--', $path]);
    }
}

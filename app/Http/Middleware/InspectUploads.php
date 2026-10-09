<?php

namespace App\Http\Middleware;

use App\Services\UploadSecurity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InspectUploads
{
    public function handle(Request $request, Closure $next): Response
    {
        $files = $this->files($request->allFiles());
        if (count($files) > config('security.uploads.max_files', 100)
            || array_sum(array_map(fn ($file) => (int) $file->getSize(), $files)) > config('security.uploads.max_request_bytes', 200 * 1024 * 1024)) {
            throw ValidationException::withMessages(['files' => 'The upload batch exceeds the permitted file count or total size.']);
        }
        $deadline = microtime(true) + min(120, max(1, (float) config('security.uploads.request_scan_seconds', 60)));
        foreach ($files as $field => $file) {
            UploadSecurity::inspect($file, $field, $deadline - microtime(true));
        }
        return $next($request);
    }

    private function files(array $values, string $prefix = ''): array
    {
        $files = [];
        foreach ($values as $key => $value) {
            $field = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if ($value instanceof UploadedFile) {
                $files[$field] = $value;
            } elseif (is_array($value)) {
                $files += $this->files($value, $field);
            }
        }
        return $files;
    }
}

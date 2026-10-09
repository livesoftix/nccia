<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Serves sensitive uploads (CNIC scans, evidence, forensic reports) only
 * through a signed, authenticated route instead of a public /storage or
 * /uploads URL. Files stay on disk where they are; the web server denies
 * direct HTTP access to those folders and this service hands out short-lived
 * signed links that the authenticated SecureFileController resolves.
 */
class SecureFileService
{
    public static function normalize(?string $path): ?string
    {
        if (!$path || preg_match('/[\x00-\x1f\\\\:%?#]/', $path) || str_starts_with($path, '/')
            || str_contains($path, '..') || str_contains($path, '//') || str_contains($path, '/./')) {
            return null;
        }
        if (!preg_match('#^(uploads/|signatures/|verification-reports/|verifications/|forensic-requests/|forensic-audio/|forensic-reports/|imports/|court-reports/|case-attachments/|enquiry-attachments/)#', $path)) {
            return null;
        }
        // Active content and executables must never be served on this origin.
        if (!preg_match('/\.(jpe?g|png|gif|webp|pdf|docx?|xlsx?|mp3|wav|ogg|m4a|aac|amr|3gp|mp4)$/i', $path)) {
            return null;
        }
        return $path;
    }

    /** Build a signed, short-lived URL for a stored relative path. */
    public static function url(?string $path, ?Model $record = null): ?string
    {
        $path = self::normalize($path);
        $user = auth()->user();
        $type = $record ? SecureFileAccessService::typeFor($record) : null;
        if (!$path || !$user instanceof User || !$record?->exists || !$type
            || !SecureFileAccessService::canView($user, $record)
            || !SecureFileAccessService::containsPath($record, $path)) {
            return null;
        }
        return URL::temporarySignedRoute('api.secure-file', now()->addMinutes(30), [
            'p' => $path, 'uid' => $user->id, 'record' => $type, 'id' => $record->getKey(),
        ]);
    }

    /**
     * Resolve a stored relative path to a real file on disk, refusing anything
     * that escapes the allowed folders (path traversal) or does not exist.
     */
    public static function resolve(string $path): ?string
    {
        $path = self::normalize($path);
        if (!$path) {
            return null;
        }
        $roots = str_starts_with($path, 'uploads/')
            ? [[public_path('uploads'), substr($path, 8)]]
            : [[Storage::disk('public')->path(''), $path], [Storage::disk('local')->path(''), $path]];
        foreach ($roots as [$base, $relative]) {
            $baseReal = realpath($base);
            $real = realpath($base . DIRECTORY_SEPARATOR . $relative);
            if ($baseReal !== false && $real !== false && is_file($real)
                && str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) {
                if (filesize($real) > config('security.uploads.max_file_bytes', 50 * 1024 * 1024)) return null;
                $detected = UploadSecurity::detectedExtension(new File($real));
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $extension = $extension === 'jpeg' ? 'jpg' : $extension;
                if (!$detected || ($extension !== $detected && !in_array([$extension, $detected], [['mp4', 'm4a'], ['m4a', 'mp4']], true))) {
                    return null;
                }
                return $real;
            }
        }

        return null;
    }
}

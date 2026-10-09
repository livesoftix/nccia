<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File;

class UploadSecurity
{
    public const DOCUMENT_TYPES = 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx';

    private const MIME_TYPES = [
        'jpg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'pdf' => ['application/pdf'], 'doc' => ['application/msword'], 'xls' => ['application/vnd.ms-excel'],
        'mp3' => ['audio/mpeg'], 'wav' => ['audio/wav', 'audio/x-wav'],
        'ogg' => ['audio/ogg', 'application/ogg'], 'm4a' => ['audio/mp4', 'audio/x-m4a'],
        'aac' => ['audio/aac'], 'amr' => ['audio/amr', 'audio/amr-wb'],
        '3gp' => ['video/3gpp', 'audio/3gpp'], 'mp4' => ['video/mp4'],
    ];

    public static function detectedExtension(File $file): ?string
    {
        $mime = $file->getMimeType();
        if (in_array($mime, ['application/zip',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)) {
            return self::officeExtension($file->getPathname());
        }
        if (in_array($mime, ['application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'], true)) {
            $extension = strtolower((string) $file->guessExtension());
            return in_array($extension, ['doc', 'xls'], true) ? $extension : null;
        }
        foreach (self::MIME_TYPES as $extension => $types) {
            if (in_array($mime, $types, true)) {
                if (str_starts_with($mime, 'image/')) {
                    $size = @getimagesize($file->getPathname());
                    if (!$size || ($size['mime'] ?? null) !== $mime || $size[0] * $size[1] > config('security.uploads.max_image_pixels', 40000000)) {
                        return null;
                    }
                }
                return $extension;
            }
        }
        return null;
    }

    private static function officeExtension(string $path): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            return null;
        }
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return null;
        }
        try {
            if ($zip->numFiles > 2048 || $zip->locateName('[Content_Types].xml') === false) {
                return null;
            }
            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = $entry['name'] ?? '';
                $total += $entry['size'] ?? 0;
                $opsys = $attributes = 0;
                $zip->getExternalAttributesIndex($index, $opsys, $attributes);
                // Validate without extracting: reject traversal, bombs and macro-enabled Office content.
                if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')
                    || preg_match('/(?:vbaProject|macros|activeX|embeddings)/i', $name)
                    || (($attributes >> 16) & 0170000) === 0120000 || ($entry['encryption_method'] ?? 0) !== 0
                    || $total > 100 * 1024 * 1024
                    || (($entry['size'] ?? 0) > 1024 * 1024 && ($entry['size'] / max(1, $entry['comp_size'])) > 100)) {
                    return null;
                }
            }
            $word = $zip->locateName('word/document.xml') !== false;
            $excel = $zip->locateName('xl/workbook.xml') !== false;
            return $word === $excel ? null : ($word ? 'docx' : 'xlsx');
        } finally {
            $zip->close();
        }
    }

    public static function inspect(UploadedFile $file, string $field, ?float $remainingSeconds = null): void
    {
        if (!$file->isValid() || $file->getSize() > config('security.uploads.max_file_bytes', 50 * 1024 * 1024)
            || !self::detectedExtension($file)) {
            throw ValidationException::withMessages([$field => 'Upload a supported image, document or audio file within the size limit.']);
        }
        app(UploadScanner::class)->scan($file, $field, $remainingSeconds);
    }

    public static function extension(UploadedFile $file, string $field = 'file'): string
    {
        $extension = self::detectedExtension($file);
        if (!$file->isValid() || !in_array($extension, explode(',', self::DOCUMENT_TYPES), true)) {
            throw ValidationException::withMessages([$field => 'Upload a supported image or document file.']);
        }
        return $extension;
    }
}

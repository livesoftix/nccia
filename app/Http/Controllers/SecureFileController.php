<?php

namespace App\Http\Controllers;

use App\Services\SecureFileService;
use App\Services\SecureFileAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Streams a sensitive upload to an authenticated user via a signed URL.
 * The route is protected by auth:sanctum (session) + the 'signed' middleware,
 * so a file can only be fetched through a link this server itself issued to an
 * authorised viewer, and only while that link is valid.
 */
class SecureFileController extends Controller
{
    public function show(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $uid = $request->query('uid');
        $type = $request->query('record');
        $path = $request->query('p', '');
        abort_unless($user && is_scalar($uid) && (string) $uid === (string) $user->id && is_string($type) && is_string($path), 403);
        $record = SecureFileAccessService::find($type, $request->query('id'));
        abort_unless($record && SecureFileAccessService::canView($user, $record), 403);
        abort_unless(SecureFileAccessService::containsPath($record, $path), 403);
        $real = SecureFileService::resolve($path);

        abort_if($real === null, 404);

        app(\App\Services\UploadScanner::class)->scanStored(new File($real));

        $mime = (new File($real))->getMimeType();
        // Images/audio remain usable in the portal. Documents are downloaded,
        // isolated from the application origin and never MIME-sniffed as HTML.
        $disposition = str_starts_with($mime, 'image/') || str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/') || $mime === 'application/ogg'
            ? 'inline' : 'attachment';
        $filename = basename($real);
        $fallback = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) ?: 'download';
        return response()->file($real, [
            'Cache-Control'           => 'private, max-age=0, no-store',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Type'            => $mime,
            'Content-Disposition'     => HeaderUtils::makeDisposition($disposition, $filename, $fallback),
            'Content-Security-Policy' => "sandbox; default-src 'none'; frame-ancestors 'none'; base-uri 'none'",
            'X-Download-Options'      => 'noopen',
        ]);
    }
}

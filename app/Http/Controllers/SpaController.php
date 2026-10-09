<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class SpaController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $candidates = array_values(array_unique(array_filter([
            public_path('react/index.html'),
            base_path('public/react/index.html'),
            isset($_SERVER['DOCUMENT_ROOT'])
                ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . '/react/index.html'
                : null,
        ])));

        foreach ($candidates as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $html = @file_get_contents($path);
            if ($html === false || $html === '') {
                continue;
            }

            $mtime = @filemtime($path);
            if ($mtime) {
                $bust = (string) $mtime;
                $html = preg_replace(
                    '#(href=")(/css/[^"?]+)(")#',
                    '$1$2?v=' . $bust . '$3',
                    $html
                ) ?? $html;
            }

            return response($html, 200, [
                'Content-Type'  => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
                'Expires'       => '0',
            ]);
        }

        return redirect('/react/index.html');
    }
}

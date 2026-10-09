<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\URL;

class QrService
{
    /**
     * HMAC token used on public verification URLs.
     */
    public function token(string $type, int|string $id): string
    {
        return substr(hash_hmac('sha256', $type . ':' . $id, (string) config('app.key')), 0, 32);
    }

    public function checkToken(string $type, int|string $id, string $token): bool
    {
        $expected = $this->token($type, $id);

        return hash_equals($expected, $token);
    }

    /**
     * Public scan URL. Phones open this and show authentic document details.
     */
    public function verifyUrl(string $type, int|string $id, ?string $noticeToken = null): string
    {
        if ($type === 'notice' && $noticeToken) {
            return URL::to('/verify/notice/' . $noticeToken);
        }

        if (in_array($type, ['complaint', 'complaint_report'], true)) {
            return URL::to('/verify/complaint/' . $id . '/' . $this->token('complaint', $id));
        }

        return URL::to('/verify/doc/' . $type . '/' . $id . '/' . $this->token($type, $id));
    }

    /**
     * Content encoded inside the QR: the public verify URL.
     * Document details are shown on the verify page so the QR stays scannable.
     */
    public function payload(string $type, int|string $id, array $fields = [], ?string $noticeToken = null): string
    {
        return $this->verifyUrl($type, $id, $noticeToken);
    }

    /**
     * Render a QR code as an <img>-ready data URI (SVG).
     */
    public function svgDataUri(string $data): string
    {
        $svg = $this->svgMarkup($data);
        if (str_starts_with($svg, 'data:')) {
            return $svg;
        }

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Raw SVG markup (for injecting into print HTML / frontend).
     */
    public function svgMarkup(string $data): string
    {
        $oldReporting = error_reporting();
        error_reporting(E_ALL & ~E_DEPRECATED);

        try {
            $options = new QROptions;
            $options->outputType = QRCode::OUTPUT_MARKUP_SVG;
            $options->scale = 5;
            $options->addQuietzone = true;
            if (property_exists($options, 'connectPaths')) {
                $options->connectPaths = true;
            }
            if (property_exists($options, 'outputBase64')) {
                $options->outputBase64 = false;
            }

            $raw = (new QRCode($options))->render($data);

            return is_string($raw) ? $raw : '';
        } catch (\Throwable $e) {
            $firstLine = strtok($data, "\n") ?: $data;
            if ($firstLine !== $data) {
                return $this->svgMarkup($firstLine);
            }
            report($e);

            return '';
        } finally {
            error_reporting($oldReporting);
        }
    }
}

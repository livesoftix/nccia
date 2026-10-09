<?php

namespace App\Services;

class ReportBrandingService
{
    /**
     * @param  list<string>|string  $relativePublicPaths
     */
    public function imageDataUri(array|string $relativePublicPaths): ?string
    {
        foreach ((array) $relativePublicPaths as $relativePublicPath) {
            $path = public_path($relativePublicPath);
            if (!is_file($path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                default => 'application/octet-stream',
            };

            return "data:{$mime};base64," . base64_encode(file_get_contents($path));
        }

        return null;
    }

    public function ncciaLogoDataUri(): ?string
    {
        return $this->imageDataUri([
            'images/images.jpg',
            'images/NCCIA.webp',
            'images/nccia_circle_logo.png',
        ]);
    }

    public function ministryLogoDataUri(): ?string
    {
        return $this->imageDataUri('images/pak-govt-logo.png');
    }
}

@php
    /** @var string $qrType */
    /** @var int|string $qrId */
    /** @var string $documentSubtitle */
    /** @var string|null $qrCaption */
    /** @var string|null $documentMeta */

    $branding = app(\App\Services\ReportBrandingService::class);
    $qrService = app(\App\Services\QrService::class);

    $ncciaLogo = $branding->ncciaLogoDataUri();
    $moiLogo = $branding->ministryLogoDataUri();
    $qrCaption = $qrCaption ?? strtoupper((string) $qrType) . '-' . $qrId;
    $qrSvg = $qrService->svgMarkup($qrService->verifyUrl($qrType, $qrId));
@endphp

<div class="nccia-letterhead">
    <table class="letterhead-table">
        <tr>
            <td class="letterhead-side letterhead-left">
                @if($ncciaLogo)
                    <img src="{{ $ncciaLogo }}" alt="NCCIA" class="letterhead-logo-img">
                @endif
            </td>
            <td class="letterhead-center">
                <div class="letterhead-agency">NCCIA</div>
                <div class="letterhead-title">National Cyber Crime Investigation Agency</div>
                <div class="letterhead-sub">Ministry of Interior &bull; Government of Pakistan</div>
            </td>
            <td class="letterhead-side letterhead-right">
                @if($moiLogo)
                    <img src="{{ $moiLogo }}" alt="Ministry of Interior" class="letterhead-logo-img">
                @endif
            </td>
        </tr>
    </table>

    <div class="letterhead-banner">{{ $documentSubtitle }}</div>

    @if(!empty($documentMeta))
        <div class="letterhead-meta">{{ $documentMeta }}</div>
    @endif

    @if($qrSvg)
        <div class="letterhead-qr">
            <div class="letterhead-qr-box">{!! $qrSvg !!}</div>
            <div class="letterhead-qr-caption">Scan to verify &bull; {{ $qrCaption }}</div>
        </div>
    @endif
</div>

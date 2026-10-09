<?php

namespace App\Services;

use Illuminate\Http\Request;

class IpDetectionService
{
    protected array $trustedProxies = [];

    public function __construct()
    {
        $this->trustedProxies = config('services.trusted_proxies', []);
    }

    public function getClientIp(Request $request): string
    {
        return $request->ip();
    }

    public function getRealIp(Request $request): ?string
    {
        $proxyHeaders = $this->getProxyHeaders($request);
        $realIp = null;

        // 1. Check client-side WebRTC candidates (catches real ISP IP behind VPN)
        $webrtcIps = (array) $request->input('client_webrtc_ips', []);
        foreach ($webrtcIps as $wip) {
            if ($this->isValidIp($wip) && !$this->isPrivateIp($wip)) {
                return $wip;
            }
        }

        // 2. Check common proxy headers in order of reliability
        $headerOrder = [
            'cf-connecting-ip',        // Cloudflare
            'x-real-ip',               // Nginx/standard
            'x-forwarded-for',         // Standard, may contain multiple IPs
            'x-client-ip',             // Some proxies
            'x-forwarded',             // Less common
            'forwarded-for',           // Less common
            'forwarded',               // RFC 7239
        ];

        foreach ($headerOrder as $header) {
            $value = $request->header($header);
            if ($value) {
                // X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
                // The first one is the original client
                $ips = array_map('trim', explode(',', $value));
                foreach ($ips as $ip) {
                    if ($this->isValidIp($ip) && !$this->isPrivateIp($ip)) {
                        $realIp = $ip;
                        break 2;
                    }
                }
            }
        }

        // If no valid public IP found in headers, check private WebRTC or fall back to request IP
        if (!$realIp && !empty($webrtcIps)) {
            $realIp = $webrtcIps[0];
        }

        if (!$realIp) {
            $requestIp = $request->ip();
            if ($this->isValidIp($requestIp)) {
                $realIp = $requestIp;
            }
        }

        return $realIp;
    }

    public function getProxyHeaders(Request $request): array
    {
        $headers = [];
        $interestingHeaders = [
            'cf-connecting-ip',
            'cf-ray',
            'x-real-ip',
            'x-forwarded-for',
            'x-forwarded-proto',
            'x-forwarded-host',
            'x-forwarded-port',
            'x-client-ip',
            'x-forwarded',
            'forwarded-for',
            'forwarded',
            'true-client-ip',          // Akamai/Cloudflare
            'x-cluster-client-ip',     // AWS ALB
        ];

        foreach ($interestingHeaders as $header) {
            $value = $request->header($header);
            if ($value) {
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    public function detectSpoofing(Request $request): bool
    {
        $clientIp = $this->getClientIp($request);
        $realIp = $this->getRealIp($request);
        $proxyHeaders = $this->getProxyHeaders($request);

        // Case 1: WebRTC or Real IP differs from connection IP (VPN or Proxy detected)
        if ($realIp && $clientIp && $realIp !== $clientIp) {
            return true;
        }

        // If we have proxy headers but no real IP found, or mismatch
        if (!empty($proxyHeaders)) {
            // Case 2: Multiple conflicting X-Forwarded-For entries (header manipulation)
            $xff = $request->header('x-forwarded-for');
            if ($xff) {
                $ips = array_map('trim', explode(',', $xff));
                $publicIps = array_filter($ips, fn($ip) => $this->isValidIp($ip) && !$this->isPrivateIp($ip));
                if (count($publicIps) > 1) {
                    return true;
                }
            }
            // Case 3: Suspicious header combinations
            if (isset($proxyHeaders['x-real-ip']) && isset($proxyHeaders['cf-connecting-ip'])) {
                if ($proxyHeaders['x-real-ip'] !== $proxyHeaders['cf-connecting-ip']) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    protected function isPrivateIp(string $ip): bool
    {
        if (!$this->isValidIp($ip)) {
            return true;
        }

        // Check for private ranges
        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '169.254.0.0/16', // Link-local
            '::1/128',        // IPv6 localhost
            'fc00::/7',       // IPv6 ULA
            'fe80::/10',      // IPv6 link-local
        ];

        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    protected function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        [$rangeIp, $cidr] = explode('/', $range);
        $mask = ~((1 << (128 - (int)$cidr)) - 1);

        // Handle IPv4
        if (strpos($ip, ':') === false) {
            $ipLong = ip2long($ip);
            $rangeLong = ip2long($rangeIp);
            $mask = (int)$mask;
            return ($ipLong & $mask) === ($rangeLong & $mask);
        }

        // IPv6 - simplified check (in production use a proper library)
        return false; // Simplified for now
    }
}
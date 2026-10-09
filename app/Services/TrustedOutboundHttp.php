<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

/** Fixed, approved service endpoints with certificate checks and bounded responses. */
class TrustedOutboundHttp
{
    public function request(string $url, string $service, int $maxBytes = 1048576): PendingRequest
    {
        $policy = (array) config('services.'.$service, []);
        $parts = parse_url($url);
        if (!$parts || array_intersect(array_keys($parts), ['user', 'pass', 'query', 'fragment'])
            || preg_match('/[\x00-\x20\\\\]/', $url)) {
            throw new \RuntimeException('Service endpoint is invalid.');
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        $approved = array_map('strtolower', (array) ($policy['allowed_hosts'] ?? []));
        if (!$host || !in_array($host, $approved, true)) {
            throw new \RuntimeException('Service endpoint is not approved.');
        }
        $loopback = in_array($host, ['127.0.0.1', '::1', 'localhost'], true);
        $localHttp = $scheme === 'http' && $loopback && !app()->environment('production')
            && ($policy['allow_local_http'] ?? false);
        if ($scheme !== 'https' && !$localHttp) {
            throw new \RuntimeException('A trusted TLS service endpoint is required.');
        }
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, (array) ($policy['allowed_ports'] ?? [443]), true)) {
            throw new \RuntimeException('Service port is not approved.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveHost($host);
        if (!$ips) {
            throw new \RuntimeException('Service address could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new \RuntimeException('Service address is invalid.');
            }
            if (!$localHttp && !($policy['allow_private_network'] ?? false) && !$this->publicAddress($ip)) {
                throw new \RuntimeException('Service address is outside the approved network.');
            }
        }
        if (!defined('CURLOPT_RESOLVE')) {
            throw new \RuntimeException('The secure HTTP transport requires the cURL extension.');
        }
        $address = str_contains($ips[0], ':') ? '['.$ips[0].']' : $ips[0];
        return Http::connectTimeout(5)->timeout(max(1, min(180, (int) ($policy['timeout'] ?? 15))))
            ->withOptions([
                'verify' => true,
                'allow_redirects' => false,
                'proxy' => '',
                'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$address]],
                'on_headers' => static function (ResponseInterface $response) use ($maxBytes) {
                    $length = $response->getHeaderLine('Content-Length');
                    if ($length !== '' && (!ctype_digit($length) || (int) $length > $maxBytes)) {
                        throw new \RuntimeException('Service response exceeded its size limit.');
                    }
                },
                'progress' => static function ($total, $downloaded) use ($maxBytes) {
                    if ($downloaded > $maxBytes) {
                        throw new \RuntimeException('Service response exceeded its size limit.');
                    }
                },
            ]);
    }

    public function body(Response $response, int $maxBytes): string
    {
        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            throw new \RuntimeException('Service response exceeded its size limit.');
        }
        return $body;
    }

    protected function resolveHost(string $host): array
    {
        // Pin a validated IPv4 address for this request, preventing a second DNS resolution.
        return array_values(array_unique(gethostbynamel($host) ?: []));
    }

    private function publicAddress(string $ip): bool
    {
        $packed = inet_pton($ip);
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $ip = inet_ntop(substr($packed, 12));
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $n = array_map('intval', explode('.', $ip));
            return !($n[0] === 0 || $n[0] >= 224 || ($n[0] === 100 && $n[1] >= 64 && $n[1] <= 127)
                || ($n[0] === 192 && $n[1] === 0) || ($n[0] === 198 && in_array($n[1], [18, 19], true))
                || ($n[0] === 198 && $n[1] === 51 && $n[2] === 100)
                || ($n[0] === 203 && $n[1] === 0 && $n[2] === 113));
        }
        return substr($packed, 0, 4) !== "\x20\x01\x0d\xb8" && ord($packed[0]) !== 255;
    }
}

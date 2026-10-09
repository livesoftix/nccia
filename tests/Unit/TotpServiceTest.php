<?php

namespace Tests\Unit;

use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_rfc_6238_sha1_vectors_including_timestamps_after_2038(): void
    {
        $totp = new TotpService;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // gitleaks:allow RFC 6238 Appendix B public test key: https://www.rfc-editor.org/rfc/rfc6238#appendix-B
        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471',
            1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $time => $expected) {
            $this->assertSame($expected, $totp->code($secret, $time, 8));
        }
    }

    public function test_invalid_and_expired_codes_are_rejected(): void
    {
        $totp = new TotpService;
        $secret = $totp->generateSecret();
        $this->assertMatchesRegularExpression('/\A[A-Z2-7]{32}\z/', $secret);
        $this->assertNull($totp->matchingCounter($secret, '12345', 1234567890));
        $this->assertNull($totp->matchingCounter($secret, ' 123456', 1234567890));
        $this->assertNull($totp->matchingCounter($secret, $totp->code($secret, 1234567890), 1234567980));
    }
}

<?php

namespace Tests\Unit;

use App\Rules\StrongPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    public function test_policy_accepts_long_unicode_passphrases_without_composition_rules_and_rejects_common_phrases(): void
    {
        foreach (['a unique long passphrase', str_repeat('ع', 128)] as $password) {
            $this->assertTrue(Validator::make(['password' => $password], ['password' => StrongPassword::rules()])->passes());
        }
        foreach (['short', 'passwordpassword', str_repeat('x', 129)] as $password) {
            $this->assertTrue(Validator::make(['password' => $password], ['password' => StrongPassword::rules()])->fails());
        }
    }

    public function test_argon_checks_the_entire_password_without_bcrypt_72_byte_truncation(): void
    {
        $first = str_repeat('a', 100) . 'first';
        $second = str_repeat('a', 100) . 'second';
        $hash = Hash::make($first);
        $this->assertSame('argon2id', password_get_info($hash)['algoName']);
        $this->assertTrue(Hash::check($first, $hash));
        $this->assertFalse(Hash::check($second, $hash));
    }
}

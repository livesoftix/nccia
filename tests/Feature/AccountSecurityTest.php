<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MfaService;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.mfa.required_roles' => [], 'security.mfa.require_all' => false, 'session.idle_timeout' => 0]);
    }

    private function account(string $role = 'operator'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['role' => $role, 'password' => 'Security-pass-29!']);
        $user->assignRole($role);
        return $user;
    }

    private function enable(User $user): string
    {
        $secret = app(TotpService::class)->generateSecret();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now()])->save();
        return $secret;
    }

    public function test_required_account_can_enroll_but_cannot_access_application_data(): void
    {
        $user = $this->account('admin');
        config(['security.mfa.required_roles' => ['admin']]);
        $this->actingAs($user)->getJson('/api/user')->assertOk()->assertJsonPath('mfa_enrollment_required', true);
        $this->getJson('/api/search?q=private')->assertStatus(423)->assertJsonPath('mfa_enrollment_required', true);
        $this->postJson('/api/auth/mfa/setup', ['password' => 'wrong'])->assertStatus(422);
        $setup = $this->postJson('/api/auth/mfa/setup', ['password' => 'Security-pass-29!'])->assertOk();
        $code = app(TotpService::class)->code($setup->json('secret'), now()->timestamp);
        $confirmed = $this->postJson('/api/auth/mfa/confirm', ['code' => $code])->assertOk();
        $this->assertCount(10, $confirmed->json('recovery_codes'));
        $this->getJson('/api/user')->assertOk()->assertJsonPath('mfa_enrollment_required', false);
        $this->deleteJson('/api/auth/mfa', ['password' => 'Security-pass-29!', 'code' => $confirmed->json('recovery_codes.0')])->assertForbidden();
        $this->assertStringNotContainsString($setup->json('secret'), DB::table('users')->where('id', $user->id)->value('mfa_secret'));
        $this->assertArrayNotHasKey('mfa_secret', $user->fresh()->toArray());
        $this->assertArrayNotHasKey('mfa_recovery_codes', $user->fresh()->toArray());
    }

    public function test_password_alone_does_not_authenticate_an_enrolled_account(): void
    {
        $user = $this->account();
        $secret = $this->enable($user);
        $credentials = ['email' => $user->email, 'password' => 'Security-pass-29!'];
        $this->postJson('/api/login', $credentials)->assertStatus(428)->assertJsonPath('mfa_required', true);
        $this->assertGuest('web');
        $this->postJson('/api/login', $credentials + ['mfa_code' => app(TotpService::class)->code($secret, now()->timestamp)])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login', $credentials + ['mfa_code' => app(TotpService::class)->code($secret, now()->timestamp)])->assertStatus(422);
        $this->assertGuest('web');
    }

    public function test_codes_are_one_time_and_recovery_codes_are_not_stored_or_serialized_in_plaintext(): void
    {
        $user = $this->account();
        $secret = $this->enable($user);
        $mfa = app(MfaService::class);
        $code = app(TotpService::class)->code($secret, now()->timestamp);
        $this->assertTrue($mfa->consume($user, $code));
        $this->assertFalse($mfa->consume($user, $code));
        $recovery = '0123456789abcdefabcd';
        $user->forceFill(['mfa_recovery_codes' => [Hash::make($recovery)]])->save();
        $raw = DB::table('users')->where('id', $user->id)->first();
        $this->assertStringNotContainsString($secret, $raw->mfa_secret);
        $this->assertStringNotContainsString($recovery, $raw->mfa_recovery_codes);
        $this->assertTrue($mfa->consume($user, $recovery));
        $this->assertFalse($mfa->consume($user, $recovery));
        $this->assertArrayNotHasKey('mfa_secret', $user->fresh()->toArray());
    }

    public function test_an_old_or_expired_setup_cannot_enable_mfa(): void
    {
        $user = $this->account();
        $secret = app(TotpService::class)->generateSecret();
        $this->actingAs($user)->withSession(['mfa_setup' => ['user_id' => $user->id, 'secret' => $secret, 'expires' => now()->subMinute()->timestamp]])
            ->postJson('/api/auth/mfa/confirm', ['code' => app(TotpService::class)->code($secret, now()->timestamp)])->assertStatus(422);
        $this->assertFalse($user->fresh()->mfa_enabled);
    }

    public function test_old_sessions_are_invalidated_after_password_or_status_changes(): void
    {
        $user = $this->account();
        $this->actingAs($user)->withSession(['security_version' => 1])->getJson('/api/user')->assertOk();
        $user->update(['password' => 'A-new-password-73!']);
        $this->getJson('/api/user')->assertUnauthorized();
        $this->actingAs($user->fresh())->withSession(['security_version' => $user->fresh()->security_version]);
        $user->update(['status' => 'suspended']);
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_enrolled_sessions_require_mfa_proof(): void
    {
        $user = $this->account();
        $this->enable($user);
        $this->actingAs($user)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_factor_enabled_during_password_authentication_does_not_create_mfa_proof(): void
    {
        $user = $this->account();
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $event->user->forceFill([
                'mfa_secret' => app(TotpService::class)->generateSecret(),
                'mfa_confirmed_at' => now(), 'security_version' => 2,
            ])->save();
        });
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Security-pass-29!'])->assertStatus(409);
        $this->assertGuest('web');
    }

    public function test_editable_designation_does_not_grant_zonal_authority(): void
    {
        $this->seed(\Database\Seeders\NcciaOfficesSeeder::class);
        $circle = \App\Models\Circle::where('code', 'LHR')->firstOrFail();
        $other = \App\Models\Circle::where('code', 'GRW')->firstOrFail();
        $user = $this->account();
        $user->update(['circle_id' => $circle->id, 'zone_id' => $circle->zone_id, 'designation' => 'Regional Director / Zonal Head']);
        $this->assertFalse($user->fresh()->isZonalHead());
        $this->assertFalse($user->fresh()->canAccessCircle($other->id));
        $this->assertTrue($user->fresh()->canAccessCircle($circle->id));
    }

    public function test_profile_credentials_require_the_current_password(): void
    {
        $user = $this->account();
        $this->actingAs($user)->putJson('/api/user/profile', [
            'password' => 'New-Strong-Password-72!', 'password_confirmation' => 'New-Strong-Password-72!',
        ])->assertUnprocessable();
        $this->assertTrue(Hash::check('Security-pass-29!', $user->fresh()->password));
        $this->putJson('/api/user/profile', ['name' => 'Updated name', 'password' => ''])->assertOk();
        $this->putJson('/api/user/profile', [
            'password' => 'New-Strong-Password-72!', 'password_confirmation' => 'New-Strong-Password-72!',
            'current_password' => 'Security-pass-29!',
        ])->assertOk();
        $this->assertTrue(Hash::check('New-Strong-Password-72!', $user->fresh()->password));
        $this->getJson('/api/user')->assertOk();
    }

    public function test_idle_timeout_and_absolute_timeout_are_enforced(): void
    {
        $user = $this->account();
        config(['session.idle_timeout' => 5]);
        $this->actingAs($user)->withSession(['last_activity' => now()->subMinutes(6)])->getJson('/api/user')->assertUnauthorized();
        config(['session.idle_timeout' => 0, 'security.session_absolute_minutes' => 60]);
        $this->actingAs($user)->withSession(['authenticated_at' => now()->subMinutes(61)->timestamp])->getJson('/api/user')->assertUnauthorized();
    }
}

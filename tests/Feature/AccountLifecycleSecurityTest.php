<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\InvestigationOfficer;
use App\Models\Otp;
use App\Models\User;
use App\Services\MfaService;
use App\Services\OtpMailService;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountLifecycleSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.mfa.required_roles' => [], 'security.mfa.require_all' => false, 'session.idle_timeout' => 0]);
        // Sanctum's table exists in deployed installations but is absent from
        // this repository's historical migrations. Create only a test fixture.
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function account(string $role = 'operator', ?int $circle = null): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['role' => $role, 'circle_id' => $circle, 'password' => 'a unique long passphrase']);
        $user->assignRole($role);
        return $user;
    }

    private function signIn(User $user, bool $mfa = false): void
    {
        $this->actingAs($user)->withSession(['security_version' => $user->security_version,
            'authenticated_at' => now()->timestamp, 'mfa_verified_user' => $mfa ? $user->id : null]);
    }

    private function circle(): Circle
    {
        $this->seed(\Database\Seeders\NcciaOfficesSeeder::class);
        return Circle::where('code', 'LHR')->firstOrFail();
    }

    private function officer(Circle $circle, ?User $user = null): InvestigationOfficer
    {
        return InvestigationOfficer::create(['name' => 'Officer', 'badge_no' => uniqid('test-', true),
            'circle' => $circle->name, 'zone' => $circle->zone->name, 'user_id' => $user?->id, 'status' => 'active']);
    }

    public function test_legacy_bcrypt_sign_in_upgrades_to_argon_without_locking_out_the_account(): void
    {
        $user = $this->account();
        DB::table('users')->where('id', $user->id)->update(['password' => password_hash('a unique long passphrase', PASSWORD_BCRYPT)]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'a unique long passphrase', 'remember' => true])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('argon2id', password_get_info($user->fresh()->password)['algoName']);
        $this->assertSame(2, $user->fresh()->security_version);
        $this->getJson('/api/user')->assertOk();
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_reset_is_single_use_revokes_tokens_and_preserves_mfa(): void
    {
        $user = $this->account();
        $secret = app(TotpService::class)->generateSecret();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now(), 'remember_token' => 'legacy-remember-value'])->save();
        $user->createToken('old-client');
        $version = $user->security_version;
        $token = str_repeat('b', 64);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Hash::make($token), 'created_at' => now()]);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'another unique long passphrase',
            'password_confirmation' => 'another unique long passphrase'];
        $this->postJson('/api/reset-password', $payload)->assertOk();
        $this->assertGuest('web');
        $fresh = $user->fresh();
        $this->assertTrue($fresh->mfa_enabled);
        $this->assertSame($version + 1, $fresh->security_version);
        $this->assertNull($fresh->remember_token);
        $this->assertSame(0, $fresh->tokens()->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->postJson('/api/reset-password', $payload)->assertStatus(422);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => $payload['password']])->assertStatus(428);
    }

    public function test_expired_reset_does_not_change_password_and_suspended_reset_is_denied(): void
    {
        $user = $this->account();
        $token = str_repeat('c', 64);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Hash::make($token), 'created_at' => now()->subHour()]);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'another unique long passphrase', 'password_confirmation' => 'another unique long passphrase'];
        $this->postJson('/api/reset-password', $payload)->assertStatus(422);
        $this->assertTrue(Hash::check('a unique long passphrase', $user->fresh()->password));
        $user->update(['status' => 'suspended']);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Hash::make($token), 'created_at' => now()]);
        $this->postJson('/api/reset-password', $payload)->assertStatus(422);
    }

    public function test_circle_manager_cannot_take_over_leadership_or_mixed_role_accounts(): void
    {
        $circle = $this->circle();
        $manager = $this->account('circle_incharge', $circle->id);
        $leader = $this->account('admin', $circle->id);
        $mixed = $this->account('operator', $circle->id);
        $mixed->assignRole('admin');
        $this->signIn($manager);
        foreach ([$leader, $mixed] as $target) {
            $this->postJson("/api/users/{$target->id}/reset-password", ['password' => 'another unique long passphrase'])->assertForbidden();
            $this->putJson("/api/users/{$target->id}", ['name' => 'Changed', 'email' => $target->email, 'role' => 'operator'])->assertForbidden();
            $this->deleteJson("/api/users/{$target->id}")->assertForbidden();
            $this->assertTrue(Hash::check('a unique long passphrase', $target->fresh()->password));
        }
        $staff = $this->account('operator', $circle->id);
        $this->postJson("/api/users/{$staff->id}/reset-password", ['password' => 'another unique long passphrase'])->assertOk();
        $this->assertTrue(Hash::check('another unique long passphrase', $staff->fresh()->password));
    }

    public function test_forensic_manager_cannot_manage_a_mixed_global_account(): void
    {
        $manager = $this->account('admin_forensic');
        $target = $this->account('admin');
        Role::findOrCreate('forensic_officer', 'web');
        $target->assignRole('forensic_officer');
        $this->signIn($manager);
        $this->postJson("/api/forensic/users/{$target->id}/reset-password", ['password' => 'another unique long passphrase'])->assertNotFound();
        $this->deleteJson("/api/forensic/users/{$target->id}")->assertNotFound();
        $this->assertTrue(Hash::check('a unique long passphrase', $target->fresh()->password));
    }

    public function test_permission_changes_revoke_existing_authentication_but_noop_edits_do_not(): void
    {
        $admin = $this->account('admin');
        $target = $this->account();
        $target->createToken('existing');
        Permission::findOrCreate('test-sensitive-permission', 'web');
        $this->signIn($admin);
        $this->putJson("/api/users/{$target->id}", ['name' => 'Changed', 'email' => $target->email, 'role' => 'operator'])->assertOk();
        $this->assertSame(1, $target->fresh()->security_version);
        $this->assertSame(1, $target->tokens()->count());
        $this->postJson("/api/users/{$target->id}/grant-permission", ['permission' => 'test-sensitive-permission'])->assertOk();
        $this->assertSame(2, $target->fresh()->security_version);
        $this->assertSame(0, $target->tokens()->count());
        $this->postJson("/api/users/{$target->id}/grant-permission", ['permission' => 'test-sensitive-permission'])->assertOk();
        $this->assertSame(2, $target->fresh()->security_version);
        $this->postJson("/api/users/{$target->id}/revoke-permission", ['permission' => 'test-sensitive-permission'])->assertOk();
        $this->assertSame(3, $target->fresh()->security_version);
    }

    public function test_recovery_regeneration_requires_both_proofs_and_invalidates_old_codes(): void
    {
        $user = $this->account();
        $secret = app(TotpService::class)->generateSecret();
        $oldCode = '0123456789abcdefabcd';
        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now(), 'mfa_recovery_codes' => [Hash::make($oldCode)]])->save();
        $version = $user->security_version;
        $this->signIn($user, true);
        $this->postJson('/api/auth/mfa/recovery-codes', ['password' => 'wrong', 'code' => $oldCode])->assertStatus(422);
        $result = $this->postJson('/api/auth/mfa/recovery-codes', ['password' => 'a unique long passphrase', 'code' => $oldCode])->assertOk();
        $this->assertCount(10, $result->json('recovery_codes'));
        $this->assertSame($version + 1, $user->fresh()->security_version);
        $this->assertFalse(app(MfaService::class)->consume($user, $oldCode));
        $this->assertTrue(app(MfaService::class)->consume($user, $result->json('recovery_codes.0')));
        $this->getJson('/api/user')->assertOk();
        $this->withSession(['security_version' => $version])->getJson('/api/user')->assertUnauthorized();
    }

    public function test_stale_mfa_setup_cannot_be_confirmed_after_a_password_change(): void
    {
        $user = $this->account();
        $this->signIn($user);
        $setup = $this->postJson('/api/auth/mfa/setup', ['password' => 'a unique long passphrase'])->assertOk();
        $user->update(['password' => 'another unique long passphrase']);
        $this->signIn($user->fresh());
        $this->postJson('/api/auth/mfa/confirm', ['code' => app(TotpService::class)->code($setup->json('secret'), now()->timestamp)])->assertStatus(409);
        $this->assertFalse($user->fresh()->mfa_enabled);
    }

    public function test_portal_grant_cannot_overwrite_an_existing_account_email(): void
    {
        $circle = $this->circle();
        $manager = $this->account('admin', $circle->id);
        $target = $this->account('admin', $circle->id);
        $officer = $this->officer($circle);
        $this->signIn($manager);
        $this->postJson("/api/investigation-officers/{$officer->id}/grant-access", ['email' => $target->email, 'password' => 'another unique long passphrase'])->assertStatus(422);
        $this->assertTrue(Hash::check('a unique long passphrase', $target->fresh()->password));
        $this->assertNull($officer->fresh()->user_id);
        $linked = $this->officer($circle, $target);
        $ci = $this->account('circle_incharge', $circle->id);
        $this->assertFalse(app(\App\Policies\InvestigationOfficerPolicy::class)->manageAccess($ci, $linked));
    }

    public function test_access_otp_is_hashed_bound_single_use_and_attempt_limited(): void
    {
        $circle = $this->circle();
        $manager = $this->account('admin', $circle->id);
        $officer = $this->officer($circle);
        $otherOfficer = $this->officer($circle);
        $email = 'new.officer@example.test';
        $captured = null;
        $this->mock(OtpMailService::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('send')->twice()->andReturnUsing(function ($to, $code) use (&$captured) {
                $captured = $code;
                return ['ok' => true, 'via' => 'test', 'error' => null];
            });
        });
        $this->signIn($manager);
        $this->postJson("/api/investigation-officers/{$officer->id}/send-otp", ['email' => $email])->assertOk();
        $record = Otp::latest('id')->firstOrFail();
        $this->assertSame('', $record->otp);
        $this->assertTrue(Hash::check($captured, $record->otp_hash));
        $this->assertLessThanOrEqual(300, now()->diffInSeconds($record->expires_at));
        $this->assertArrayNotHasKey('otp_hash', $record->toArray());
        $this->postJson("/api/investigation-officers/{$otherOfficer->id}/verify-otp", ['email' => $email, 'otp' => $captured])->assertStatus(422);
        $wrong = $captured === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/investigation-officers/{$officer->id}/verify-otp", ['email' => $email, 'otp' => $wrong])->assertStatus(422);
        }
        $this->postJson("/api/investigation-officers/{$officer->id}/verify-otp", ['email' => $email, 'otp' => $captured])->assertStatus(422);
        $this->postJson("/api/investigation-officers/{$officer->id}/send-otp", ['email' => $email])->assertOk();
        $result = $this->postJson("/api/investigation-officers/{$officer->id}/verify-otp", ['email' => $email, 'otp' => $captured])->assertOk();
        $this->assertSame(32, strlen($result->json('password')));
        $this->assertNotNull($officer->fresh()->user_id);
        $this->postJson("/api/investigation-officers/{$officer->id}/verify-otp", ['email' => $email, 'otp' => $captured])->assertStatus(422);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ApprovalSetting;
use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\Message;
use App\Models\User;
use App\Models\WarrantRequest;

require_once __DIR__ . '/../../database/seeders/CircleTenancySeeder.php';

use Database\Seeders\CircleTenancySeeder;
use Database\Seeders\NcciaOfficesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Locks in every security fix made during the hardening pass. If any of these
 * regress, the build fails. This is the objective evidence for the controls.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NcciaOfficesSeeder::class);
        $this->seed(CircleTenancySeeder::class);
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->app->usePublicPath(\Illuminate\Support\Facades\Storage::disk('local')->path('test-webroot'));
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** C1 — a non-image (e.g. .php) accused file upload must be rejected. */
    public function test_php_file_upload_is_blocked(): void
    {
        $operator = $this->user('fdo.lhr@nccia.gov.pk');

        $payload = [
            'complainant_name' => 'T', 'cnic' => '12345-1234567-1', 'contact_no' => '03001234567',
            'address' => 'x', 'report_date' => '2026-01-01', 'received_via' => 'Email',
            'received_from' => 'Self', 'offence_type' => 'Fraud', 'occurrence_date' => '2026-01-01',
            'description' => 'test', 'entry_time' => '2026-01-01 10:00:00',
            'initial_accused' => json_encode([['name' => 'A', 'cnic' => '11111-1111111-1', 'father_name' => 'B']]),
            'accused_picture' => [UploadedFile::fake()->create('evil.php', 8, 'application/x-httpd-php')],
        ];

        $this->actingAs($operator)
            ->postJson('/api/complaints', $payload)
            ->assertStatus(422);
    }

    /** C1 — a genuine image upload must still succeed. */
    public function test_image_upload_still_works(): void
    {
        $operator = $this->user('fdo.lhr@nccia.gov.pk');

        $payload = [
            'complainant_name' => 'T', 'cnic' => '12345-1234567-1', 'contact_no' => '03001234567',
            'address' => 'x', 'report_date' => '2026-01-01', 'received_via' => 'Email',
            'received_from' => 'Self', 'offence_type' => 'Fraud', 'occurrence_date' => '2026-01-01',
            'description' => 'test', 'entry_time' => '2026-01-01 10:00:00',
            'initial_accused' => json_encode([['name' => 'A', 'cnic' => '11111-1111111-1', 'father_name' => 'B']]),
            'accused_picture' => [UploadedFile::fake()->image('photo.png', 10, 10)],
        ];

        $this->actingAs($operator)
            ->postJson('/api/complaints', $payload)
            ->assertStatus(201);
    }

    /** C2 — an operator must not be able to file a court case. */
    public function test_operator_cannot_create_court_case(): void
    {
        $this->actingAs($this->user('fdo.lhr@nccia.gov.pk'))
            ->postJson('/api/court-cases', [])
            ->assertStatus(403);
    }

    /** C2 — an investigation officer must not be able to file a court case. */
    public function test_io_cannot_create_court_case(): void
    {
        $this->actingAs($this->user('io.lhr@nccia.gov.pk'))
            ->postJson('/api/court-cases', [])
            ->assertStatus(403);
    }

    /** C2 — an authorised role still reaches the controller (validation, not 403). */
    public function test_admin_can_reach_court_case_controller(): void
    {
        $this->actingAs($this->user('admin@admin.com'))
            ->postJson('/api/court-cases', [])
            ->assertStatus(422);
    }

    /** C3 — a suspended account cannot log in. */
    public function test_suspended_user_cannot_login(): void
    {
        $u = $this->user('fdo.grw@nccia.gov.pk');
        $u->update(['status' => 'suspended']);

        $this->postJson('/api/login', [
            'email' => 'fdo.grw@nccia.gov.pk', 'password' => 'password123',
        ])->assertStatus(403);
    }

    /** H2 — an officer from another circle cannot read a case chat thread. */
    public function test_cross_circle_case_chat_is_blocked(): void
    {
        $lhrCase = CaseFile::create(['fir_no' => 'SEC-FIR-1', 'status' => 'in_progress', 'circle_id' => $this->circleId('LHR')]);
        Message::create([
            'sender_id' => $this->user('admin@admin.com')->id,
            'case_file_id' => $lhrCase->id, 'case_number' => 'SEC-FIR-1',
            'message' => 'confidential', 'is_read' => true,
        ]);

        $this->actingAs($this->user('fdo.grw@nccia.gov.pk'))
            ->getJson('/api/messages/cases/' . $lhrCase->id . '/thread')
            ->assertStatus(403);
    }

    /** H4 — a user cannot pull the signed link for a complaint outside their circle. */
    public function test_document_qr_idor_is_blocked(): void
    {
        $grwComplaint = Complaint::where('circle_id', $this->circleId('GRW'))->first();
        $this->assertNotNull($grwComplaint, 'need a GRW complaint from the seeder');

        $this->actingAs($this->user('fdo.lhr@nccia.gov.pk'))
            ->getJson('/api/documents/complaint/' . $grwComplaint->id . '/qr')
            ->assertStatus(403);
    }

    /** B — warrant print is gated when the admin sets the action to mandatory. */
    public function test_warrant_approval_gate(): void
    {
        $enquiry = $this->makeEnquiry('LHR');

        // Open by default → reaches controller (not 403).
        $this->actingAs($this->user('admin@admin.com'))
            ->getJson('/api/enquiries/' . $enquiry->id . '/arrest-warrant-print')
            ->assertStatus(200);

        // Admin makes it mandatory.
        ApprovalSetting::where('action_key', 'arrest_warrant')->update(['requirement' => 'mandatory']);
        ApprovalSetting::flushCache();

        // Now blocked without an approved request.
        $this->actingAs($this->user('admin@admin.com'))
            ->getJson('/api/enquiries/' . $enquiry->id . '/arrest-warrant-print')
            ->assertStatus(403);

        // Officer requests, CI approves → allowed again.
        WarrantRequest::create([
            'action_key' => 'arrest_warrant', 'enquiry_id' => $enquiry->id,
            'circle_id' => $this->circleId('LHR'), 'requested_by' => $this->user('io.lhr@nccia.gov.pk')->id,
            'status' => 'approved', 'approved_by' => $this->user('ci.lhr@nccia.gov.pk')->id,
        ]);
        ApprovalSetting::flushCache();

        $this->actingAs($this->user('admin@admin.com'))
            ->getJson('/api/enquiries/' . $enquiry->id . '/arrest-warrant-print')
            ->assertStatus(200);
    }

    /** A — proclamation enforces the 30-day rule of s.87 CrPC. */
    public function test_proclamation_30_day_rule(): void
    {
        $case = CaseFile::create(['fir_no' => 'SEC-FIR-2', 'status' => 'in_progress', 'circle_id' => $this->circleId('LHR')]);

        // Too soon → rejected.
        $this->actingAs($this->user('admin@admin.com'))
            ->postJson('/api/cases/' . $case->id . '/proclamations', [
                'accused_name' => 'X', 'appear_by_date' => now()->addDays(5)->toDateString(),
            ])->assertStatus(422);

        // 30+ days → accepted.
        $this->actingAs($this->user('admin@admin.com'))
            ->postJson('/api/cases/' . $case->id . '/proclamations', [
                'accused_name' => 'X', 'appear_by_date' => now()->addDays(45)->toDateString(),
            ])->assertStatus(201);
    }

    /** H3 — a crafted designation must NOT grant a role (privilege escalation). */
    public function test_designation_does_not_grant_role(): void
    {
        $u = new User([
            'name' => 'Sneaky', 'email' => 'sneaky@test.local',
            'role' => 'operator', 'designation' => 'Senior Circle Incharge, Investigation Wing',
        ]);

        // Escalation vectors must all be closed:
        $this->assertFalse($u->hasRole('circle_incharge'), 'designation must not grant circle_incharge');
        $this->assertFalse($u->hasRole('investigation_officer'), 'designation must not grant investigation_officer');
        $this->assertFalse($u->hasRole('admin'), 'designation must not grant admin');

        // Its real, validated role still works, and the safe alias holds.
        $this->assertTrue($u->hasRole('operator'), 'the real role column must still resolve');
    }

    /** H3 — a genuine circle incharge (by role column) still resolves. */
    public function test_real_role_still_resolves(): void
    {
        $this->assertTrue($this->user('ci.lhr@nccia.gov.pk')->hasRole('circle_incharge'));
        $this->assertTrue($this->user('io.lhr@nccia.gov.pk')->hasRole('investigation_officer'));
    }

    /** H1 — sensitive files are served only via a signed, authenticated route. */
    public function test_secure_file_route_requires_auth_and_signature(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $path = 'verification-reports/evidence/secret.pdf';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, '%PDF-1.4 test');
        $admin = $this->user('admin@admin.com');
        $complaint = Complaint::firstOrFail();
        $complaint->update(['attachment' => $path]);
        $this->actingAs($admin);
        $signed = \App\Services\SecureFileService::url($path, $complaint);
        $this->app['auth']->forgetGuards();

        // unauthenticated → blocked (not 200)
        $this->getJson($signed)->assertStatus(401);

        // authenticated + valid signature → served
        $this->actingAs($this->user('admin@admin.com'))
            ->get($signed)->assertStatus(200);

        // authenticated but tampered signature → rejected
        $tampered = $signed . 'x';
        $this->actingAs($this->user('admin@admin.com'))
            ->get($tampered)->assertStatus(403);
    }

    /** H1 — path traversal through the secure route is refused. */
    public function test_secure_file_blocks_traversal(): void
    {
        $bad = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'api.secure-file', now()->addHour(), ['p' => '../../.env']
        );
        // Blocked either by the attack-sanitiser (400) or the resolver (404) — never served.
        $status = $this->actingAs($this->user('admin@admin.com'))->get($bad)->status();
        $this->assertContains($status, [400, 403, 404], "traversal must be refused, got {$status}");
    }

    /** #1 — the audit ledger records actions and detects tampering. */
    public function test_audit_ledger_records_and_detects_tampering(): void
    {
        $before = \App\Models\AuditLedger::count();

        // An observed model write creates a ledger entry.
        \App\Models\CaseFile::create(['fir_no' => 'LEDGER-1', 'status' => 'in_progress', 'circle_id' => $this->circleId('LHR')]);
        $this->assertGreaterThan($before, \App\Models\AuditLedger::count(), 'a ledger entry must be recorded');

        // The chain is intact right after legitimate writes.
        $this->assertTrue(\App\Services\AuditLedgerService::verify()['ok'], 'chain should be intact');

        // Tamper with a stored entry directly in the database (bypassing the app).
        $lastId = \App\Models\AuditLedger::max('id');
        \Illuminate\Support\Facades\DB::table('audit_ledger')->where('id', $lastId)
            ->update(['properties' => json_encode(['tampered' => true])]);

        $result = \App\Services\AuditLedgerService::verify();
        $this->assertFalse($result['ok'], 'tampering must be detected');
        $this->assertSame($lastId, $result['broken_at']);
    }

    // ================= Requirement #2 — Role-Based Data Locking =================

    /** #2 — the operator who registered a complaint loses edit rights once it is forwarded. */
    public function test_complaint_locks_for_operator_after_forward(): void
    {
        $operator = $this->user('fdo.lhr@nccia.gov.pk');
        $complaint = Complaint::where('circle_id', $this->circleId('LHR'))->firstOrFail();
        $complaint->update(['operator_id' => $operator->id, 'status' => 'incomplete']);

        // While in intake the operator can still edit.
        $this->assertTrue($operator->can('update', $complaint->fresh()));

        // Once forwarded to verification it is read-only for the operator.
        $complaint->update(['status' => 'assigned']);
        $this->assertFalse($operator->can('update', $complaint->fresh()));

        // Admin retains an audited correction path.
        $this->assertTrue($this->user('admin@admin.com')->can('update', $complaint->fresh()));
    }

    /** #2 — a verification officer is locked out of their own report once it is submitted. */
    public function test_verification_locks_for_officer_after_submit(): void
    {
        $vo = $this->user('vo.lhr@nccia.gov.pk');
        $verification = $this->makeVerification('LHR', $vo, 'in_progress');

        // Working → editable by the owning VO, returns the controller (not 423).
        $this->assertTrue($vo->can('update', $verification->fresh()));

        // Submitted → read-only. The HTTP path returns a clear 423 Locked.
        $verification->update(['status' => 'submitted']);
        $this->assertFalse($vo->can('update', $verification->fresh()));

        $this->actingAs($vo)
            ->putJson('/api/verifications/' . $verification->id, [
                'verification_officer_id' => $vo->id,
                'priority_type' => 'normal',
                'recommendation' => 'trying to edit after submit',
            ])->assertStatus(423);
    }

    /** #2 — the next officer in the chain can VIEW but cannot EDIT a prior officer's entry. */
    public function test_next_officer_cannot_edit_prior_officers_verification(): void
    {
        $vo = $this->user('vo.lhr@nccia.gov.pk');
        $eo = $this->user('eo.lhr@nccia.gov.pk'); // the downstream officer
        $verification = $this->makeVerification('LHR', $vo, 'submitted');

        // The enquiry officer never owned the verification stage → no edit rights.
        $this->assertFalse($eo->can('update', $verification->fresh()));
    }

    /** #2 — an enquiry officer is locked out once the CFR is submitted (HTTP 423). */
    public function test_enquiry_locks_for_officer_after_cfr(): void
    {
        $eo = $this->user('eo.lhr@nccia.gov.pk');
        $enquiry = $this->makeEnquiry('LHR');
        $enquiry->update(['enquiry_officer_id' => $eo->id, 'status' => 'in_progress']);

        $this->assertTrue($eo->can('update', $enquiry->fresh()));

        $enquiry->update(['status' => 'cfr_submitted']);
        $this->assertFalse($eo->can('update', $enquiry->fresh()));

        $this->actingAs($eo)
            ->putJson('/api/enquiries/' . $enquiry->id, ['recommendation' => 'late edit'])
            ->assertStatus(423);
    }

    /** #2 — an investigation officer is locked out once the case is registered (HTTP 423). */
    public function test_case_locks_for_io_after_registration(): void
    {
        $io = $this->user('io.lhr@nccia.gov.pk');
        $case = CaseFile::create([
            'fir_no' => 'LOCK-FIR-1', 'status' => 'in_progress',
            'circle_id' => $this->circleId('LHR'), 'investigation_officer_id' => $io->id,
        ]);

        $this->assertTrue($io->can('update', $case->fresh()));

        $case->update(['status' => 'registered']);
        $this->assertFalse($io->can('update', $case->fresh()));

        $this->actingAs($io)
            ->putJson('/api/cases/' . $case->id, ['remarks' => 'late edit'])
            ->assertStatus(423);
    }

    /** #2 — admin override: a supervisor is NEVER locked out of a forwarded record. */
    public function test_admin_can_still_edit_forwarded_record(): void
    {
        $admin = $this->user('admin@admin.com');
        $enquiry = $this->makeEnquiry('LHR');
        $enquiry->update(['status' => 'cfr_submitted']);

        $this->assertFalse($enquiry->fresh()->isLockedFor($admin), 'admin must keep an audited correction path');

        $status = $this->actingAs($admin)
            ->putJson('/api/enquiries/' . $enquiry->id, ['recommendation' => 'supervisor correction'])
            ->status();
        $this->assertNotContains($status, [403, 423], "admin edit of a forwarded record must not be locked, got {$status}");
    }

    // ============ Requirement #3 — Real-Time Sync & Data Integrity ============

    /** #3 — the sync pulse reports which modules changed since the client's last head. */
    public function test_sync_pulse_reports_changed_modules(): void
    {
        $admin = $this->user('admin@admin.com');

        // Baseline head.
        $head = (int) $this->actingAs($admin)->getJson('/api/sync/pulse')->json('head');

        // A change is recorded (observed model → audit ledger entry).
        CaseFile::create(['fir_no' => 'PULSE-1', 'status' => 'in_progress', 'circle_id' => $this->circleId('LHR')]);

        $resp = $this->actingAs($admin)->getJson('/api/sync/pulse?since=' . $head);
        $resp->assertStatus(200);
        $this->assertGreaterThan($head, $resp->json('head'), 'head must advance after a change');
        $this->assertGreaterThanOrEqual(1, $resp->json('changes'));
        $this->assertContains('cases', $resp->json('changed'), "the 'cases' module must be flagged as changed");
    }

    /** #3 — a clean pulse (nothing changed since head) reports no changed modules. */
    public function test_sync_pulse_quiet_when_nothing_changed(): void
    {
        $admin = $this->user('admin@admin.com');
        $head = (int) $this->actingAs($admin)->getJson('/api/sync/pulse')->json('head');

        $resp = $this->actingAs($admin)->getJson('/api/sync/pulse?since=' . $head);
        $resp->assertStatus(200);
        $this->assertSame([], $resp->json('changed'));
        $this->assertSame(0, $resp->json('changes'));
    }

    /** #3 — optimistic concurrency: a stale update (someone else saved first) is rejected with 409. */
    public function test_stale_update_is_rejected_with_conflict(): void
    {
        $eo = $this->user('eo.lhr@nccia.gov.pk');
        $enquiry = $this->makeEnquiry('LHR');
        $enquiry->update(['enquiry_officer_id' => $eo->id, 'status' => 'in_progress']);

        // The token the client loaded.
        $staleToken = $enquiry->fresh()->updated_at->toIso8601String();

        // Someone else saves in the meantime → stored record becomes newer.
        $enquiry->forceFill(['updated_at' => now()->addMinutes(2)])->saveQuietly();

        $this->actingAs($eo)
            ->putJson('/api/enquiries/' . $enquiry->id, [
                'recommendation'      => 'my edit',
                'expected_updated_at' => $staleToken,
            ])->assertStatus(409);
    }

    /** #3 — a fresh update carrying the current token is NOT treated as a conflict. */
    public function test_fresh_update_with_current_token_is_not_a_conflict(): void
    {
        $eo = $this->user('eo.lhr@nccia.gov.pk');
        $enquiry = $this->makeEnquiry('LHR');
        $enquiry->update(['enquiry_officer_id' => $eo->id, 'status' => 'in_progress']);

        $currentToken = $enquiry->fresh()->updated_at->toIso8601String();

        $status = $this->actingAs($eo)
            ->putJson('/api/enquiries/' . $enquiry->id, [
                'recommendation'      => 'my edit',
                'expected_updated_at' => $currentToken,
            ])->status();

        $this->assertNotSame(409, $status, "a current-token update must not be a conflict, got {$status}");
    }

    // ---- helpers ----

    private function circleId(string $code): int
    {
        return \App\Models\Circle::where('code', $code)->value('id');
    }

    private function makeVerification(string $circleCode, User $officer, string $status): \App\Models\Verification
    {
        $complaint = Complaint::where('circle_id', $this->circleId($circleCode))->firstOrFail();

        return \App\Models\Verification::create([
            'complaint_id'            => $complaint->id,
            'verification_officer_id' => $officer->id,
            'status'                  => $status,
        ]);
    }

    private function makeEnquiry(string $circleCode): Enquiry
    {
        $complaint = Complaint::where('circle_id', $this->circleId($circleCode))->first();
        return Enquiry::create([
            'complaint_id' => $complaint?->id,
            'enquiry_number' => 'SEC-ENQ-' . uniqid(),
            'status' => 'in_progress',
        ]);
    }
}

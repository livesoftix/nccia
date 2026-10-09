<?php

namespace Tests\Feature;

use App\Models\{Circle, Complaint, Enquiry, EnquiryAttachment, ForensicRequest, User};
use App\Services\{SecureFileAccessService, SecureFileService};
use Database\Seeders\{CircleTenancySeeder, NcciaOfficesSeeder, RolesAndPermissionsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage, URL};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

require_once __DIR__ . '/../../database/seeders/CircleTenancySeeder.php';

class SecureFileSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NcciaOfficesSeeder::class);
        $this->seed(CircleTenancySeeder::class);
        Storage::fake('public');
        Storage::fake('local');
        // Upload tests must never create or replace production files.
        $this->app->usePublicPath(Storage::disk('local')->path('test-webroot'));
    }

    private function user(string $circle): User
    {
        return User::where('email', 'ci.' . strtolower($circle) . '@nccia.gov.pk')->firstOrFail();
    }

    private function attachment(string $circle = 'LHR'): Complaint
    {
        $complaint = Complaint::where('circle_id', Circle::where('code', $circle)->value('id'))->firstOrFail();
        $path = 'verification-reports/evidence/' . $circle . '.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 test evidence');
        $complaint->update(['attachment' => $path]);
        return $complaint;
    }

    public function test_signed_file_is_bound_to_the_issuing_user(): void
    {
        $complaint = $this->attachment();
        $this->actingAs($this->user('LHR'));
        $link = SecureFileService::url($complaint->attachment, $complaint);
        $this->assertNotNull($link);
        $this->get($link)->assertOk();
        $this->actingAs($this->user('GRW'))->get($link)->assertForbidden();
    }

    public function test_signed_link_rechecks_circle_access_after_issuance(): void
    {
        $complaint = $this->attachment();
        $this->actingAs($this->user('LHR'));
        $link = SecureFileService::url($complaint->attachment, $complaint);
        $complaint->update(['circle_id' => Circle::where('code', 'GRW')->value('id')]);
        $this->get($link)->assertForbidden();
    }

    public function test_valid_signature_cannot_fetch_an_unassociated_path(): void
    {
        $complaint = $this->attachment();
        $user = $this->user('LHR');
        $other = $this->attachment('GRW');
        $link = URL::temporarySignedRoute('api.secure-file', now()->addMinutes(30), [
            'p' => $other->attachment, 'uid' => $user->id, 'record' => 'complaint', 'id' => $complaint->id,
        ]);
        $this->actingAs($user)->get($link)->assertForbidden();
    }

    public function test_suspended_and_revoked_users_cannot_download_old_links(): void
    {
        $complaint = $this->attachment();
        $user = $this->user('LHR');
        $this->actingAs($user);
        $link = SecureFileService::url($complaint->attachment, $complaint);
        $user->update(['status' => 'suspended']);
        $this->assertFalse(SecureFileAccessService::canView($user, $complaint));
        $this->getJson($link)->assertUnauthorized();
        $user->update(['status' => 'active']);
        $user->syncRoles([]);
        $this->assertFalse(SecureFileAccessService::canView($user, $complaint));
        $this->getJson($link)->assertUnauthorized();
    }

    public function test_expired_and_tampered_links_are_rejected(): void
    {
        config(['session.idle_timeout' => 0]);
        $complaint = $this->attachment();
        $this->actingAs($this->user('LHR'));
        $link = SecureFileService::url($complaint->attachment, $complaint);
        $this->get($link . 'x')->assertForbidden();
        $this->travel(31)->minutes();
        $this->get($link)->assertForbidden();
    }

    public function test_only_registered_safe_upload_paths_can_be_resolved(): void
    {
        foreach (['../.env', 'uploads/../.env', 'uploads\\test.pdf', 'C:/private.pdf',
            'uploads/test.php', 'uploads/test.html', 'uploads/test.svg', 'https://example.com/test.pdf',
            'private.pdf', 'uploads/%2e%2e/test.pdf'] as $path) {
            $this->assertNull(SecureFileService::resolve($path), $path);
        }
    }

    public function test_client_cannot_retain_a_foreign_record_file(): void
    {
        $complaint = $this->attachment('GRW');
        $this->expectException(ValidationException::class);
        SecureFileAccessService::validateRetainedPaths($this->user('LHR'), [
            ['file_path' => $complaint->attachment],
        ]);
    }

    public function test_retaining_an_accessible_attachment_still_works(): void
    {
        $complaint = $this->attachment();
        SecureFileAccessService::validateRetainedPaths($this->user('LHR'), [['file_path' => $complaint->attachment]]);
        $this->actingAs($this->user('LHR'));
        $this->assertNotNull(SecureFileService::url($complaint->attachment, $complaint));
    }

    public function test_unassigned_forensic_examiner_does_not_receive_a_file_url(): void
    {
        $examiner = User::factory()->create();
        $examiner->assignRole('forensic_team');
        $request = ForensicRequest::create([
            'request_no' => 'TEST-SECURE-1', 'destination' => 'forensic', 'status' => 'assigned',
            'assigned_to' => $this->user('LHR')->id, 'submitted_by' => $this->user('LHR')->id,
            'attachment_path' => 'forensic-requests/test.pdf',
        ]);
        $this->actingAs($examiner);
        $this->assertNull($request->attachment_url);
        $request->update(['assigned_to' => $examiner->id]);
        $this->assertNotNull($request->attachment_url);
    }

    public function test_nested_enquiry_executable_upload_is_rejected(): void
    {
        $complaint = $this->attachment();
        $enquiry = Enquiry::create(['complaint_id' => $complaint->id, 'enquiry_number' => 'SECURE-ENQ-1', 'status' => 'in_progress']);
        $this->actingAs($this->user('LHR'))->putJson('/api/enquiries/' . $enquiry->id, [
            'activities' => json_encode([['type' => 'diaries', 'description' => 'test']]),
            'activity_attachments' => [UploadedFile::fake()->createWithContent('payload.php', '<?php echo "payload";')],
        ])->assertUnprocessable()->assertJsonValidationErrors('activity_attachments.0');
    }

    public function test_misleading_client_extension_is_replaced_with_detected_image_type(): void
    {
        $image = UploadedFile::fake()->image('photo.jpg');
        $file = new UploadedFile($image->getPathname(), 'photo.php', 'image/jpeg', UPLOAD_ERR_OK, true);
        $controller = new \App\Http\Controllers\EnquiryController;
        $method = new \ReflectionMethod($controller, 'moveFile');
        $path = $method->invoke($controller, $file, 'witnesses');
        $this->assertMatchesRegularExpression('#^uploads/witnesses/[a-zA-Z0-9]+\.jpg$#', $path);
        $this->assertFileExists(public_path($path));
    }

    public function test_enquiry_attachment_serialization_preserves_private_download_access(): void
    {
        $complaint = $this->attachment();
        $enquiry = Enquiry::create(['complaint_id' => $complaint->id, 'enquiry_number' => 'SECURE-ENQ-2', 'status' => 'in_progress']);
        $path = 'enquiry-attachments/private.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 private evidence');
        $attachment = EnquiryAttachment::create([
            'enquiry_id' => $enquiry->id, 'title' => 'Test evidence', 'file_path' => $path,
            'uploaded_by' => $this->user('LHR')->id,
        ]);
        $this->actingAs($this->user('LHR'));
        $payload = $attachment->toArray();
        $this->assertNotNull($payload['file_path_url']);
        $this->get($payload['file_path_url'])->assertOk();
    }

    public function test_import_batch_identifier_cannot_control_a_directory(): void
    {
        $this->expectException(ValidationException::class);
        app(\App\Services\ComplaintPdfImportService::class)->storeUploads([], $this->user('LHR'), '../another-folder');
    }

    public function test_narrative_json_does_not_establish_attachment_ownership(): void
    {
        $complaint = $this->attachment();
        $foreign = $this->attachment('GRW');
        $complaint->update(['initial_accused' => [['name' => 'Test', 'notes' => [$foreign->attachment]]]]);
        $this->actingAs($this->user('LHR'));
        $this->assertNull(SecureFileService::url($foreign->attachment, $complaint));
    }

    public function test_complaint_attachment_lists_keep_each_registered_file_downloadable(): void
    {
        $complaint = $this->attachment();
        $path = $complaint->attachment;
        $complaint->update(['attachment' => json_encode([$path])]);
        $this->actingAs($this->user('LHR'));
        $link = SecureFileService::url($path, $complaint);
        $this->assertNotNull($link);
        $this->get($link)->assertOk();
    }

    public function test_active_html_content_cannot_be_served_under_a_pdf_extension(): void
    {
        Storage::disk('public')->put('verification-reports/evidence/spoof.pdf', '<html><script>alert(1)</script></html>');
        $this->assertNull(SecureFileService::resolve('verification-reports/evidence/spoof.pdf'));
    }

    public function test_allowed_mime_cannot_be_mislabeled_as_a_different_file_type(): void
    {
        $image = UploadedFile::fake()->image('test.jpg');
        Storage::disk('public')->put('verification-reports/evidence/spoof.pdf', file_get_contents($image->getPathname()));
        $this->assertNull(SecureFileService::resolve('verification-reports/evidence/spoof.pdf'));
    }

    public function test_document_download_is_sandboxed_and_uses_attachment_disposition(): void
    {
        $complaint = $this->attachment();
        $this->actingAs($this->user('LHR'));
        $response = $this->get(SecureFileService::url($complaint->attachment, $complaint))->assertOk();
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox;', $response->headers->get('Content-Security-Policy'));
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_signature_links_are_private_and_bound_to_the_owner(): void
    {
        $owner = $this->user('LHR');
        $image = UploadedFile::fake()->image('signature.png');
        Storage::disk('public')->put('signatures/private.png', file_get_contents($image->getPathname()));
        $owner->update(['signature' => 'signatures/private.png']);
        $this->actingAs($owner);
        $link = SecureFileService::url($owner->signature, $owner);
        $this->assertNotNull($link);
        $this->get($link)->assertOk();
        $this->actingAs($this->user('GRW'));
        $this->assertNull(SecureFileService::url($owner->signature, $owner));
        $this->get($link)->assertForbidden();
    }

    public function test_legacy_file_download_is_blocked_when_the_scanner_reports_malware(): void
    {
        $complaint = $this->attachment();
        config(['security.uploads.scan_required' => true]);
        $scanner = new class extends \App\Services\UploadScanner {
            protected function process(string $path): \Symfony\Component\Process\Process
            {
                return new \Symfony\Component\Process\Process([PHP_BINARY, '-r', 'exit(1);']);
            }
        };
        $this->app->instance(\App\Services\UploadScanner::class, $scanner);
        $this->actingAs($this->user('LHR'));
        $this->getJson(SecureFileService::url($complaint->attachment, $complaint))
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }
}

<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Circle;
use App\Models\ComplaintPdfImport;
use App\Models\CourtCase;
use App\Models\User;
use App\Services\ClientError;

require_once __DIR__ . '/../../database/seeders/CircleTenancySeeder.php';

use Database\Seeders\CircleTenancySeeder;
use Database\Seeders\NcciaOfficesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the October 2026 security audit (F-02 … F-05).
 */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NcciaOfficesSeeder::class);
        $this->seed(CircleTenancySeeder::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** A direct (no-enquiry) case owned by the Gujranwala circle, with a court case. */
    private function gujranwalaCourtCase(): CourtCase
    {
        $circle = Circle::where('code', 'GRW')->firstOrFail();
        $case = CaseFile::create([
            'enquiry_id'  => null,
            'direct_info' => ['circle_id' => $circle->id, 'reference_no' => 'AUDIT-1'],
            'fir_no'      => 'GRW-F-AUDIT/2026',
            'status'      => 'registered',
        ]);

        return CourtCase::create([
            'case_id'     => $case->id,
            'court_name'  => 'Sessions Court',
            'filing_date' => '2026-01-01',
            'status'      => 'filed',
        ]);
    }

    // ── F-02: court cases and hearings respect circle isolation ─────────────

    public function test_other_circle_cannot_mutate_or_read_court_case(): void
    {
        $courtCase = $this->gujranwalaCourtCase();
        $ciLahore = $this->user('ci.lhr@nccia.gov.pk');

        $this->actingAs($ciLahore, 'sanctum')
            ->putJson("/api/court-cases/{$courtCase->id}", ['court_name' => 'Tampered'])
            ->assertNotFound();
        $this->actingAs($ciLahore, 'sanctum')
            ->postJson("/api/court-cases/{$courtCase->id}/verdict", ['verdict' => 'acquittal', 'verdict_date' => '2026-02-01'])
            ->assertNotFound();
        $this->actingAs($ciLahore, 'sanctum')
            ->getJson("/api/court-cases/{$courtCase->id}/hearings")
            ->assertNotFound();
        $this->actingAs($ciLahore, 'sanctum')
            ->postJson("/api/court-cases/{$courtCase->id}/hearings", ['hearing_date' => '2026-02-01', 'type' => 'notice'])
            ->assertNotFound();

        $courtCase->refresh();
        $this->assertSame('Sessions Court', $courtCase->court_name);
        $this->assertSame('filed', $courtCase->status);
        $this->assertSame(0, $courtCase->verdicts()->count());
        $this->assertSame(0, $courtCase->hearings()->count());
    }

    public function test_other_circle_cannot_file_court_case_on_foreign_case(): void
    {
        $courtCase = $this->gujranwalaCourtCase();

        $this->actingAs($this->user('ci.lhr@nccia.gov.pk'), 'sanctum')
            ->postJson('/api/court-cases', [
                'case_id' => $courtCase->case_id, 'court_name' => 'X', 'filing_date' => '2026-01-01',
            ])
            ->assertNotFound();

        $this->assertSame(1, CourtCase::count());
    }

    public function test_own_circle_can_still_manage_court_case(): void
    {
        $courtCase = $this->gujranwalaCourtCase();
        $ciGujranwala = $this->user('ci.grw@nccia.gov.pk');

        $this->actingAs($ciGujranwala, 'sanctum')
            ->putJson("/api/court-cases/{$courtCase->id}", ['court_name' => 'District Court'])
            ->assertOk();
        $this->actingAs($ciGujranwala, 'sanctum')
            ->postJson("/api/court-cases/{$courtCase->id}/hearings", ['hearing_date' => '2026-02-01', 'type' => 'notice'])
            ->assertCreated();
        $this->actingAs($ciGujranwala, 'sanctum')
            ->getJson("/api/court-cases/{$courtCase->id}/hearings")
            ->assertOk()
            ->assertJsonCount(1);
    }

    // ── F-03: PDF imports are role- and circle-scoped ──────────────────────

    private function importBy(User $uploader): ComplaintPdfImport
    {
        return ComplaintPdfImport::create([
            'user_id'           => $uploader->id,
            'original_filename' => 'complaint.pdf',
            'stored_path'       => 'imports/complaint.pdf',
            'status'            => ComplaintPdfImport::STATUS_PENDING,
        ]);
    }

    public function test_roles_outside_complaint_intake_cannot_list_imports(): void
    {
        $this->importBy($this->user('fdo.lhr@nccia.gov.pk'));

        $this->actingAs($this->user('vo.lhr@nccia.gov.pk'), 'sanctum')
            ->getJson('/api/complaint-pdf-imports')
            ->assertForbidden();
        $this->actingAs($this->user('io.lhr@nccia.gov.pk'), 'sanctum')
            ->getJson('/api/complaint-pdf-imports/stats')
            ->assertForbidden();
    }

    public function test_imports_from_another_circle_are_hidden(): void
    {
        $lahoreImport = $this->importBy($this->user('fdo.lhr@nccia.gov.pk'));
        $gujranwalaOperator = $this->user('fdo.grw@nccia.gov.pk');

        $this->actingAs($gujranwalaOperator, 'sanctum')
            ->getJson('/api/complaint-pdf-imports')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->actingAs($gujranwalaOperator, 'sanctum')
            ->getJson('/api/complaint-pdf-imports/stats')
            ->assertOk()
            ->assertJsonPath('total', 0);
        $this->actingAs($gujranwalaOperator, 'sanctum')
            ->getJson("/api/complaint-pdf-imports/{$lahoreImport->id}")
            ->assertNotFound();
        $this->actingAs($gujranwalaOperator, 'sanctum')
            ->postJson("/api/complaint-pdf-imports/{$lahoreImport->id}/apply")
            ->assertNotFound();
    }

    public function test_same_circle_still_sees_its_imports(): void
    {
        $import = $this->importBy($this->user('fdo.lhr@nccia.gov.pk'));

        $this->actingAs($this->user('ci.lhr@nccia.gov.pk'), 'sanctum')
            ->getJson('/api/complaint-pdf-imports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $import->id);
        $this->actingAs($this->user('ci.lhr@nccia.gov.pk'), 'sanctum')
            ->getJson("/api/complaint-pdf-imports/{$import->id}")
            ->assertOk();
    }

    // ── F-04: internal exception details never reach the client ────────────

    public function test_database_errors_are_replaced_with_a_reference(): void
    {
        $e = new QueryException('sqlite', 'select * from secret_table where x = ?', [1], new \PDOException('no such column: x'));

        $message = ClientError::message($e);

        $this->assertStringNotContainsString('secret_table', $message);
        $this->assertStringNotContainsString('no such column', $message);
        $this->assertMatchesRegularExpression('/Reference: [A-Z0-9]{8}$/', $message);
    }

    public function test_intentional_user_messages_pass_through(): void
    {
        $this->assertSame('Officer is not in your circle.', ClientError::message(new \RuntimeException('Officer is not in your circle.')));
        $this->assertSame('Bad input.', ClientError::message(new \InvalidArgumentException('Bad input.')));
    }

    // ── F-05: list endpoints cap page size ────────────────────────────────

    public function test_enquiry_page_size_is_capped(): void
    {
        $this->actingAs($this->user('ci.lhr@nccia.gov.pk'), 'sanctum')
            ->getJson('/api/enquiries?per_page=1000000')
            ->assertOk()
            ->assertJsonPath('per_page', 100);
    }
}

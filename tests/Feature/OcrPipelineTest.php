<?php

namespace Tests\Feature;

use App\Jobs\FinalizeOcrImport;
use App\Jobs\ProcessOcrChunk;
use App\Models\Circle;
use App\Models\Complaint;
use App\Models\ComplaintPdfImport;
use App\Models\ComplaintPdfImportPage;
use App\Models\User;
use App\Services\Ocr\OcrPipeline;
use App\Services\Ocr\OcrRecordWriter;

require_once __DIR__ . '/../../database/seeders/CircleTenancySeeder.php';

use Database\Seeders\CircleTenancySeeder;
use Database\Seeders\NcciaOfficesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end tests of the circle-scoped OCR pipeline using the real local Python
 * engine on synthetic PDFs (invented names and numbers only).
 */
class OcrPipelineTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ocr.python' => env('OCR_TEST_PYTHON', 'python'), 'ocr.chunk_pages' => 2]);
        $health = Process::path(base_path('python'))->run([config('ocr.python'), '-m', 'nccia_ocr', 'health']);
        if (!$health->successful()) {
            $this->markTestSkipped('Local Python OCR engine not available: ' . trim($health->output() . $health->errorOutput()));
        }

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NcciaOfficesSeeder::class);
        $this->seed(CircleTenancySeeder::class);
        Storage::fake('local');
        $this->app->usePublicPath(Storage::disk('local')->path('test-webroot'));
        $this->fixtures = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr-fixtures-' . uniqid();
        mkdir($this->fixtures);
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtures) && is_dir($this->fixtures)) {
            array_map('unlink', glob($this->fixtures . '/*') ?: []);
            rmdir($this->fixtures);
        }
        parent::tearDown();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function circle(string $code): Circle
    {
        return Circle::where('code', $code)->firstOrFail();
    }

    /** Build a synthetic PDF with the Python fixture helper. */
    private function pdf(int $seq, array $options = []): UploadedFile
    {
        $out = $this->fixtures . DIRECTORY_SEPARATOR . "doc{$seq}-" . uniqid() . '.pdf';
        $args = [config('ocr.python'), '-m', 'tests.make_fixture', $out, '--seq', (string) $seq,
            '--kind', $options['kind'] ?? 'text', '--circle', $options['circle'] ?? $this->circle('LHR')->name,
            '--pages', (string) ($options['pages'] ?? 1)];
        foreach ($options['drop'] ?? [] as $drop) {
            array_push($args, '--drop', $drop);
        }
        foreach ($options['append'] ?? [] as $line) {
            array_push($args, '--append', $line);
        }
        Process::path(base_path('python'))->run($args)->throw();

        return new UploadedFile($out, "report-{$seq}.pdf", 'application/pdf', null, true);
    }

    private function complaintIn(Circle $circle, string $cnic): Complaint
    {
        return Complaint::create([
            'complainant_name' => 'Existing Person', 'cnic' => $cnic, 'contact_no' => '3001231234', 'address' => 'Synthetic address',
            'report_date' => '2026-01-01', 'received_via' => 'Email', 'received_from' => 'Self', 'offence_type' => 'Fraud',
            'occurrence_date' => '2026-01-01', 'description' => 'Synthetic', 'operator_name' => 'Test', 'operator_designation' => 'Test',
            'entry_time' => now(), 'circle_id' => $circle->id,
        ]);
    }

    private function upload(User $user, Circle $circle, UploadedFile $file)
    {
        return $this->actingAs($user, 'sanctum')->post('/api/ocr-imports', [
            'circle_id' => $circle->id, 'files' => [$file],
        ], ['Accept' => 'application/json']);
    }

    // ── Circle assignment & authorization ─────────────────────────────

    public function test_upload_is_processed_and_imported_into_the_selected_circle(): void
    {
        $lhr = $this->circle('LHR');
        $response = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $lhr, $this->pdf(1))->assertStatus(202);

        $import = ComplaintPdfImport::findOrFail($response->json('imports.0.id'));
        $this->assertSame(ComplaintPdfImport::STATUS_IMPORTED, $import->status, json_encode($import->review_reasons));
        $this->assertSame($lhr->id, $import->circle_id);
        $complaint = Complaint::findOrFail($import->complaint_id);
        $this->assertSame($lhr->id, $complaint->circle_id);
        $this->assertSame('35202-1000001-1', $complaint->cnic);
        $this->assertSame('3001000001', $complaint->contact_no);
        $this->assertSame('E/1/2026', $complaint->diary_no);
        $this->assertSame('pdf_import', $complaint->source);
        $this->assertNotNull($import->verification_report_id);
        $this->assertSame(1, $import->field_results['victim_cnic']['page']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'ocr_import', 'subject_id' => $complaint->id]);
    }

    public function test_cannot_upload_into_a_circle_the_user_does_not_belong_to(): void
    {
        $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('GRW'), $this->pdf(2))->assertForbidden();
        $this->assertSame(0, ComplaintPdfImport::count());
        $this->assertSame([], Storage::disk('local')->allFiles('ocr'));
    }

    public function test_other_circle_cannot_read_review_retry_or_download(): void
    {
        $response = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), $this->pdf(3));
        $id = $response->json('imports.0.id');
        $grw = $this->user('ci.grw@nccia.gov.pk');

        $this->actingAs($grw, 'sanctum')->getJson("/api/ocr-imports/{$id}")->assertNotFound();
        $this->actingAs($grw, 'sanctum')->getJson("/api/ocr-imports/{$id}/pages/1")->assertNotFound();
        $this->actingAs($grw, 'sanctum')->get("/api/ocr-imports/{$id}/file", ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/pdf'])->assertNotFound();
        $this->actingAs($this->user('ci.lhr@nccia.gov.pk'), 'sanctum')
            ->get("/api/ocr-imports/{$id}/file", ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/pdf'])
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($grw, 'sanctum')->putJson("/api/ocr-imports/{$id}/review", ['fields' => ['victim_full_name' => 'Hijacked Name'], 'approve' => true])->assertNotFound();
        $this->actingAs($grw, 'sanctum')->postJson("/api/ocr-imports/{$id}/retry")->assertNotFound();
        $this->assertSame(0, $this->actingAs($grw, 'sanctum')->getJson('/api/ocr-imports')->json('total'));
        $this->assertNotSame('Hijacked Name', Complaint::find(ComplaintPdfImport::find($id)->complaint_id)?->complainant_name);
    }

    public function test_same_circle_and_hq_can_view(): void
    {
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), $this->pdf(4))->json('imports.0.id');
        $this->actingAs($this->user('ci.lhr@nccia.gov.pk'), 'sanctum')->getJson("/api/ocr-imports/{$id}")->assertOk();
        $this->actingAs($this->user('admin@admin.com'), 'sanctum')->getJson("/api/ocr-imports/{$id}")->assertOk();
    }

    public function test_document_naming_another_circle_is_flagged_not_moved(): void
    {
        $lhr = $this->circle('LHR');
        $grw = $this->circle('GRW');
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $lhr, $this->pdf(5, ['circle' => $grw->name]))->json('imports.0.id');

        $import = ComplaintPdfImport::findOrFail($id);
        $this->assertSame(ComplaintPdfImport::STATUS_NEEDS_REVIEW, $import->status);
        $this->assertSame($lhr->id, $import->circle_id);
        $this->assertNull($import->complaint_id);
        $this->assertTrue(collect($import->review_reasons)->contains(fn ($r) => str_contains($r, 'mentions circle')));
        $this->assertSame(0, Complaint::where('circle_id', $grw->id)->where('source', 'pdf_import')->count());
    }

    // ── Duplicates & idempotency ───────────────────────────────────────

    public function test_duplicate_file_in_same_circle_is_detected(): void
    {
        $user = $this->user('fdo.lhr@nccia.gov.pk');
        $file = $this->pdf(6);
        $first = $this->upload($user, $this->circle('LHR'), $file)->json('imports.0');
        $second = $this->upload($user, $this->circle('LHR'), new UploadedFile($file->getRealPath(), 'renamed.pdf', 'application/pdf', null, true))->json('imports.0');

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, ComplaintPdfImport::count());
        $this->assertSame(1, Complaint::where('source', 'pdf_import')->count());
    }

    public function test_same_file_in_another_circle_is_independent_and_does_not_leak(): void
    {
        $admin = $this->user('admin@admin.com');
        $file = $this->pdf(7, ['circle' => '']);
        $a = $this->upload($admin, $this->circle('LHR'), $file)->json('imports.0');
        $b = $this->upload($admin, $this->circle('GRW'), new UploadedFile($file->getRealPath(), 'x.pdf', 'application/pdf', null, true))->json('imports.0');

        $this->assertFalse($b['duplicate']);
        $this->assertNotSame($a['id'], $b['id']);
        $this->assertSame($this->circle('GRW')->id, ComplaintPdfImport::find($b['id'])->circle_id);
    }

    public function test_reprocessing_does_not_duplicate_pages_or_records(): void
    {
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), $this->pdf(8, ['pages' => 3]))->json('imports.0.id');
        $import = ComplaintPdfImport::findOrFail($id);
        $this->assertSame(ComplaintPdfImport::STATUS_IMPORTED, $import->status);

        (new ProcessOcrChunk($id))->handle(app(OcrPipeline::class));
        (new FinalizeOcrImport($id))->handle(app(OcrPipeline::class));
        app(OcrRecordWriter::class)->write($import, ['victim_full_name' => 'x'], $import->user);

        $this->assertSame(3, ComplaintPdfImportPage::where('complaint_pdf_import_id', $id)->count());
        $this->assertSame(1, Complaint::where('source', 'pdf_import')->count());
    }

    public function test_interrupted_processing_resumes_from_checkpoint(): void
    {
        DB::table('jobs')->delete();
        config(['queue.default' => 'database']);
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), $this->pdf(9, ['pages' => 5]))->json('imports.0.id');
        $import = ComplaintPdfImport::findOrFail($id);
        $pipeline = app(OcrPipeline::class);

        $this->assertTrue($pipeline->processNextChunk($import));          // pages 1-2, then "crash"
        $this->assertSame(2, ComplaintPdfImportPage::where('complaint_pdf_import_id', $id)->count());
        $firstUpdated = ComplaintPdfImportPage::where('complaint_pdf_import_id', $id)->where('page_no', 1)->value('updated_at');

        config(['queue.default' => 'sync']);
        (new ProcessOcrChunk($id))->handle($pipeline);                     // resume on a fresh job

        $import->refresh();
        $this->assertSame(ComplaintPdfImport::STATUS_IMPORTED, $import->status, json_encode($import->review_reasons));
        $this->assertSame(5, ComplaintPdfImportPage::where('complaint_pdf_import_id', $id)->count());
        $this->assertEquals($firstUpdated, ComplaintPdfImportPage::where('complaint_pdf_import_id', $id)->where('page_no', 1)->value('updated_at'));
    }

    // ── Validation, review & audit ─────────────────────────────────────

    public function test_missing_required_field_goes_to_review_and_is_never_invented(): void
    {
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), $this->pdf(10, ['drop' => ['Mobile Number']]))->json('imports.0.id');
        $import = ComplaintPdfImport::findOrFail($id);

        $this->assertSame(ComplaintPdfImport::STATUS_NEEDS_REVIEW, $import->status);
        $this->assertNull($import->complaint_id);
        $this->assertNull($import->field_results['victim_phone']['value']);
        $this->assertTrue(collect($import->review_reasons)->contains(fn ($r) => str_starts_with($r, 'victim_phone')));
        $this->assertSame(0, Complaint::where('source', 'pdf_import')->count());
    }

    public function test_reviewer_correction_is_validated_audited_and_imports_into_same_circle(): void
    {
        $lhr = $this->circle('LHR');
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $lhr, $this->pdf(11, ['drop' => ['Mobile Number']]))->json('imports.0.id');
        $ci = $this->user('ci.lhr@nccia.gov.pk');

        $this->actingAs($ci, 'sanctum')->putJson("/api/ocr-imports/{$id}/review", ['fields' => ['victim_phone' => '12345'], 'approve' => true])
            ->assertStatus(422);
        $this->actingAs($ci, 'sanctum')->putJson("/api/ocr-imports/{$id}/review", ['fields' => ['victim_phone' => '0300-7654321'], 'approve' => true])
            ->assertOk()->assertJsonPath('status', 'imported');

        $import = ComplaintPdfImport::findOrFail($id);
        $complaint = Complaint::findOrFail($import->complaint_id);
        $this->assertSame($lhr->id, $complaint->circle_id);
        $this->assertSame('3007654321', $complaint->contact_no);
        $this->assertSame($ci->id, $import->reviewed_by);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'ocr_review', 'subject_id' => $id, 'description' => 'OCR fields corrected']);
    }

    public function test_conflicting_values_require_review(): void
    {
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), $this->pdf(12, ['append' => ['CNIC No: 61101-7654321-9']]))->json('imports.0.id');
        $import = ComplaintPdfImport::findOrFail($id);
        $this->assertSame(ComplaintPdfImport::STATUS_NEEDS_REVIEW, $import->status);
        $this->assertTrue(collect($import->review_reasons)->contains(fn ($r) => str_contains($r, 'conflict')));
    }

    public function test_possible_duplicate_complaint_is_only_searched_within_the_circle(): void
    {
        $grw = $this->circle('GRW');
        $lhr = $this->circle('LHR');
        $foreign = $this->complaintIn($grw, '35202-1000013-3');

        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $lhr, $this->pdf(13))->json('imports.0.id');
        $this->assertSame(ComplaintPdfImport::STATUS_IMPORTED, ComplaintPdfImport::find($id)->status, 'other circle must not block or link');
        $this->assertSame('Existing Person', $foreign->fresh()->complainant_name, 'other circle record untouched');

        $this->complaintIn($lhr, '35202-1000014-4');
        $id2 = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $lhr, $this->pdf(14))->json('imports.0.id');
        $import = ComplaintPdfImport::find($id2);
        $this->assertSame(ComplaintPdfImport::STATUS_NEEDS_REVIEW, $import->status);
        $this->assertTrue(collect($import->review_reasons)->contains(fn ($r) => str_contains($r, 'possible duplicate')));
    }

    public function test_link_to_complaint_in_another_circle_is_refused(): void
    {
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), $this->pdf(15, ['drop' => ['Mobile Number']]))->json('imports.0.id');
        $foreign = $this->complaintIn($this->circle('GRW'), '35202-9999999-1');

        $this->actingAs($this->user('ci.lhr@nccia.gov.pk'), 'sanctum')->putJson("/api/ocr-imports/{$id}/review", [
            'fields' => ['victim_phone' => '03001112223'], 'approve' => true, 'link_complaint_id' => $foreign->id,
        ])->assertStatus(422);
        $this->assertNull(ComplaintPdfImport::find($id)->complaint_id);
    }

    // ── Malformed input & failures ─────────────────────────────────────

    public function test_malformed_pdf_is_rejected_without_retry_loop(): void
    {
        $path = $this->fixtures . '/broken.pdf';
        file_put_contents($path, "%PDF-1.7\n" . str_repeat('garbage ', 50));
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), new UploadedFile($path, 'broken.pdf', 'application/pdf', null, true))
            ->json('imports.0.id');

        $import = ComplaintPdfImport::findOrFail($id);
        $this->assertSame(ComplaintPdfImport::STATUS_FAILED, $import->status);
        $this->assertStringStartsWith('Rejected:', $import->error_message);
        $this->assertSame(1, $import->attempts);
    }

    public function test_non_pdf_upload_is_refused_by_validation(): void
    {
        $path = $this->fixtures . '/note.pdf';
        file_put_contents($path, 'plain text pretending to be a pdf');
        $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), new UploadedFile($path, 'note.pdf', 'application/pdf', null, true))
            ->assertStatus(422);
    }

    public function test_database_failure_during_write_leaves_no_partial_records(): void
    {
        $this->app->bind(OcrRecordWriter::class, fn ($app) => new class($app->make(\App\Services\TrackingNumberGenerator::class)) extends OcrRecordWriter {
            public function write(ComplaintPdfImport $import, array $values, User $actor, ?int $linkComplaintId = null): ComplaintPdfImport
            {
                return DB::transaction(function () use ($import, $values, $actor) {
                    Complaint::create(['complainant_name' => 'partial', 'cnic' => $values['victim_cnic'], 'contact_no' => '3000000000',
                        'address' => 'x', 'report_date' => now(), 'received_via' => 'x', 'received_from' => 'x', 'offence_type' => 'x',
                        'occurrence_date' => now(), 'description' => 'x', 'operator_name' => 'x', 'operator_designation' => 'x',
                        'entry_time' => now(), 'circle_id' => $import->circle_id]);
                    throw new \RuntimeException('simulated database failure');
                });
            }
        });

        $response = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), $this->pdf(16));
        $import = ComplaintPdfImport::where('file_hash', '!=', null)->latest('id')->firstOrFail();

        $this->assertSame(ComplaintPdfImport::STATUS_FAILED, $import->status);
        $this->assertNull($import->complaint_id);
        $this->assertSame(0, Complaint::where('complainant_name', 'partial')->count());
        $this->assertNotNull($response->json('imports.0'));
    }

    public function test_scanned_pdf_is_ocr_processed(): void
    {
        $id = $this->upload($this->user('fdo.lhr@nccia.gov.pk'), $this->circle('LHR'), $this->pdf(17, ['kind' => 'scan']))->json('imports.0.id');
        $import = ComplaintPdfImport::findOrFail($id);
        $this->assertTrue($import->used_ocr);
        $this->assertSame('ocr', ComplaintPdfImportPage::where('complaint_pdf_import_id', $id)->value('method'));
        $this->assertSame('35202-1000017-7', $import->field_results['victim_cnic']['value']);
        $this->assertContains($import->status, [ComplaintPdfImport::STATUS_IMPORTED, ComplaintPdfImport::STATUS_NEEDS_REVIEW]);
    }
}

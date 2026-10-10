<?php

namespace App\Services\Ocr;

use App\Models\Complaint;
use App\Models\ComplaintPdfImport;
use App\Models\User;
use App\Models\VerificationReport;
use App\Services\TrackingNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Persists validated OCR values into the existing complaints / verification_reports
 * tables, always inside the import's own circle.
 */
class OcrRecordWriter
{
    public function __construct(private TrackingNumberGenerator $tracking)
    {
    }

    /**
     * Complaints in the same circle that look like the same matter. Never searches other circles.
     *
     * @return \Illuminate\Support\Collection<int, Complaint>
     */
    public function possibleDuplicates(ComplaintPdfImport $import, array $values)
    {
        if (!$import->circle_id) {
            return collect();
        }

        return Complaint::where('circle_id', $import->circle_id)
            ->where(function ($q) use ($values) {
                if (!empty($values['inquiry_no'])) {
                    $q->orWhere('diary_no', $values['inquiry_no']);
                }
                if (!empty($values['victim_cnic'])) {
                    $q->orWhere('cnic', $values['victim_cnic']);
                }
            })
            ->when(empty($values['inquiry_no']) && empty($values['victim_cnic']), fn ($q) => $q->whereRaw('1 = 0'))
            ->limit(5)
            ->get(['id', 'tracking_no', 'diary_no', 'complainant_name', 'cnic', 'created_at']);
    }

    /**
     * @param  array<string, mixed>  $values  validated values from OcrDecision
     * @param  int|null  $linkComplaintId  reviewer decision: attach to this existing complaint instead of creating one
     */
    public function write(ComplaintPdfImport $import, array $values, User $actor, ?int $linkComplaintId = null): ComplaintPdfImport
    {
        if (!$import->circle_id) {
            throw new \LogicException('OCR imports must carry an authorized circle.');
        }

        return DB::transaction(function () use ($import, $values, $actor, $linkComplaintId) {
            $locked = ComplaintPdfImport::whereKey($import->id)->lockForUpdate()->firstOrFail();

            // Idempotent: a finished import is never written twice.
            if ($locked->status === ComplaintPdfImport::STATUS_IMPORTED && $locked->complaint_id) {
                return $locked;
            }

            if ($linkComplaintId) {
                $complaint = Complaint::where('circle_id', $locked->circle_id)->findOrFail($linkComplaintId);
                $created = false;
            } else {
                $complaint = $this->createComplaint($locked, $values, $actor);
                $created = true;
            }

            $report = $this->createVerificationReport($locked, $complaint, $values, $actor);

            activity('ocr_import')
                ->performedOn($complaint)
                ->causedBy($actor)
                ->withProperties([
                    'import_id' => $locked->id,
                    'file_hash' => $locked->file_hash,
                    'circle_id' => $locked->circle_id,
                    'action' => $created ? 'created_complaint' : 'linked_existing_complaint',
                    'verification_report_id' => $report?->id,
                ])
                ->log($created ? 'Complaint created from OCR import' : 'OCR import linked to existing complaint');

            $locked->update([
                'status' => ComplaintPdfImport::STATUS_IMPORTED,
                'complaint_id' => $complaint->id,
                'verification_report_id' => $report?->id,
                'import_result' => [
                    'complaint_id' => $complaint->id,
                    'tracking_no' => $complaint->tracking_no,
                    'created' => $created,
                    'verification_report_id' => $report?->id,
                    'verification_report_skipped' => $report ? null : 'document lacks tracking no, phone, crime category or city',
                ],
                'error_message' => null,
                'processed_at' => now(),
            ]);

            return $locked->fresh();
        }, 3);
    }

    private function createComplaint(ComplaintPdfImport $import, array $values, User $actor): Complaint
    {
        $reportDate = $values['report_date'] ?? $values['verification_date'] ?? $values['assignment_date'];
        $contact = substr(preg_replace('/^0/', '', $values['victim_phone']), 0, 10);

        $payload = array_filter([
            'complainant_name' => $values['victim_full_name'],
            'father_name' => $values['victim_father_name'] ?? null,
            'gender' => $values['victim_gender'] ?? null,
            'cnic' => $values['victim_cnic'],
            'contact_no' => $contact,
            'contact_country_code' => '+92',
            'email' => $values['victim_email'] ?? null,
            'address' => $values['victim_address'],
            'post_address' => $values['victim_permanent_address'] ?? $values['victim_address'],
            'profession' => $values['victim_occupation'] ?? null,
            'report_date' => $reportDate,
            'occurrence_date' => $values['assignment_date'] ?? $reportDate,
            'diary_no' => $values['inquiry_no'] ?? null,
            'offence_type' => $values['crime_category'],
            'description' => $values['crime_description'] ?? $values['crime_category'],
            'amount_involved' => $values['amount_involved'] ?? null,
            // Provenance (system facts, not document claims).
            'received_via' => 'PDF Import (OCR)',
            'received_from' => 'Document import',
            'operator_name' => $actor->name,
            'operator_designation' => $actor->designation ?: ($actor->role ?: 'Operator'),
            'operator_remarks' => sprintf('OCR import #%d: %s (sha256 %s)', $import->id, $import->original_filename, substr((string) $import->file_hash, 0, 16)),
            'entry_time' => now(),
            'scrutiny_result' => 'complete',
            'status' => 'complete',
            'source' => 'pdf_import',
            'user_id' => $actor->id,
            'operator_id' => $actor->id,
            'circle_id' => $import->circle_id,
            'attachment' => $this->copyAttachment($import),
        ], fn ($v) => $v !== null && $v !== '');

        $circle = $import->circle;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $payload['tracking_no'] = $this->tracking->generate($circle);
            try {
                return DB::transaction(fn () => Complaint::create($payload));
            } catch (QueryException $e) {
                if ($attempt === 9 || !str_contains($e->getMessage(), 'tracking_no')) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not allocate a tracking number.');
    }

    private function createVerificationReport(ComplaintPdfImport $import, Complaint $complaint, array $values, User $actor): ?VerificationReport
    {
        foreach (['tracking_no', 'victim_phone', 'crime_category', 'city'] as $needed) {
            if (empty($values[$needed])) {
                return null; // the table requires these; never fabricate them
            }
        }

        $existing = VerificationReport::where('complaint_id', $complaint->id)
            ->where('tracking_no', $values['tracking_no'])
            ->first();
        if ($existing) {
            return $existing;
        }

        return VerificationReport::create(array_filter([
            'complaint_id' => $complaint->id,
            'tracking_no' => $values['tracking_no'],
            'inquiry_no' => $values['inquiry_no'] ?? null,
            'assignment_date' => $values['assignment_date'] ?? null,
            'verification_date' => $values['verification_date'] ?? null,
            'victim_name' => $values['victim_full_name'],
            'victim_father_name' => $values['victim_father_name'] ?? null,
            'victim_gender' => $values['victim_gender'] ?? null,
            'victim_occupation' => $values['victim_occupation'] ?? null,
            'victim_cnic' => $values['victim_cnic'],
            'victim_country_code' => '+92',
            'victim_phone' => $values['victim_phone'],
            'victim_email' => $values['victim_email'] ?? null,
            'crime_category' => $values['crime_category'],
            'crime_description' => $values['crime_description'] ?? null,
            'city' => $values['city'],
            'recommendation_short' => $values['recommendation'] ?? null,
            'created_by' => $actor->id,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /** Copy the source PDF to the complaint attachment area (served only via signed secure-file links). */
    private function copyAttachment(ComplaintPdfImport $import): ?string
    {
        $disk = Storage::disk(config('ocr.disk'));
        if (!$disk->exists($import->stored_path)) {
            return null;
        }
        $relative = 'uploads/complaints/ocr-' . substr((string) $import->file_hash, 0, 24) . '.pdf';
        $target = public_path($relative);
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        if (!is_file($target)) {
            $in = $disk->readStream($import->stored_path);
            $out = fopen($target, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }

        return $relative;
    }
}

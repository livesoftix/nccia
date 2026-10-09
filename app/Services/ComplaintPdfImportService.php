<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintPdfImport;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\VerificationReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplaintPdfImportService
{
    public function __construct(
        private ComplaintPdfExtractor $extractor,
    ) {}

    public function storeUploads(array $files, User $user, ?string $batchId = null): array
    {
        $batchId = $batchId ?: (string) Str::uuid();
        if (!preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $batchId)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['batch_id' => 'Use letters, numbers, hyphens or underscores for the batch identifier.']);
        }
        $imports = [];

        foreach ($files as $file) {
            $original = $file->getClientOriginalName();
            $path = $file->storeAs(
                'imports/complaint-pdfs/' . $batchId,
                Str::slug(pathinfo($original, PATHINFO_FILENAME)) . '-' . Str::random(6) . '.pdf',
                'public'
            );

            $imports[] = $this->createImportRow($user, $batchId, $original, $path);
        }

        return ['batch_id' => $batchId, 'imports' => $imports];
    }

    /**
     * Register PDFs already on disk (CLI bulk). $inPlace keeps the original path (no copy).
     *
     * @param  list<string>  $absolutePaths
     * @return array{batch_id: string, imports: list<ComplaintPdfImport>, skipped: int, reused: int, skipped_names: list<string>}
     */
    public function registerLocalFiles(array $absolutePaths, User $user, string $batchId, bool $inPlace = true, bool $skipExisting = true): array
    {
        $imports = [];
        $skipped = 0;
        $reused = 0;
        $skippedNames = [];

        $absolutePaths = array_values(array_filter(array_map(
            fn ($p) => $this->normalizeLocalPath($p),
            $absolutePaths
        )));

        $byName = [];
        $byPath = [];
        if ($absolutePaths) {
            $names = array_values(array_unique(array_map('basename', $absolutePaths)));
            $existing = ComplaintPdfImport::query()
                ->where(function ($q) use ($names, $absolutePaths) {
                    $q->whereIn('original_filename', $names)
                        ->orWhereIn('stored_path', $absolutePaths);
                })
                ->orderByDesc('id')
                ->get();

            foreach ($existing as $row) {
                $key = strtolower((string) $row->original_filename);
                $byName[$key] ??= $row;
                $byPath[(string) $row->stored_path] ??= $row;
            }
        }

        $active = [
            ComplaintPdfImport::STATUS_IMPORTED,
            ComplaintPdfImport::STATUS_PENDING,
            ComplaintPdfImport::STATUS_PROCESSING,
            ComplaintPdfImport::STATUS_EXTRACTED,
        ];

        foreach ($absolutePaths as $abs) {
            $original = basename($abs);
            $row = $byPath[$abs] ?? $byName[strtolower($original)] ?? null;

            if ($row && $row->status === ComplaintPdfImport::STATUS_FAILED) {
                $stored = $inPlace ? $abs : $row->stored_path;
                $row->update([
                    'user_id'       => $user->id,
                    'batch_id'      => $batchId,
                    'stored_path'   => $stored,
                    'status'        => ComplaintPdfImport::STATUS_PENDING,
                    'error_message' => null,
                    'processed_at'  => null,
                ]);
                $imports[] = $row->fresh();
                $reused++;
                $byName[strtolower($original)] = $row;
                $byPath[$abs] = $row;
                continue;
            }

            if ($skipExisting && $row && in_array($row->status, $active, true)) {
                $skipped++;
                if (count($skippedNames) < 15) {
                    $skippedNames[] = $original . ' (' . $row->status . ')';
                }
                continue;
            }

            if ($inPlace) {
                $stored = $abs;
            } else {
                $destRel = 'imports/complaint-pdfs/' . $batchId . '/'
                    . Str::slug(pathinfo($original, PATHINFO_FILENAME)) . '-' . Str::random(6) . '.pdf';
                Storage::disk('public')->put($destRel, file_get_contents($abs));
                $stored = $destRel;
            }

            $created = $this->createImportRow($user, $batchId, $original, $stored);
            $imports[] = $created;
            $byName[strtolower($original)] = $created;
            $byPath[$abs] = $created;
        }

        return [
            'batch_id'       => $batchId,
            'imports'        => $imports,
            'skipped'        => $skipped,
            'reused'         => $reused,
            'skipped_names'  => $skippedNames,
        ];
    }

    public function absolutePdfPath(ComplaintPdfImport $import): string
    {
        $p = (string) $import->stored_path;
        if ($p !== '' && $this->isAbsolutePath($p) && is_file($p)) {
            return $p;
        }

        return Storage::disk('public')->path($p);
    }

    private function createImportRow(User $user, string $batchId, string $original, string $storedPath): ComplaintPdfImport
    {
        return ComplaintPdfImport::create([
            'user_id'           => $user->id,
            'batch_id'          => $batchId,
            'original_filename' => $original,
            'stored_path'       => $storedPath,
            'inquiry_ref'       => $this->extractor->inquiryRefFromFilename($original),
            'status'            => ComplaintPdfImport::STATUS_PENDING,
        ]);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    private function normalizeLocalPath(string $path): string
    {
        $real = realpath($path);

        return $real !== false ? $real : $path;
    }

    public function process(ComplaintPdfImport $import, ?string $ocrText = null): ComplaintPdfImport
    {
        @set_time_limit(300);

        $import->update(['status' => ComplaintPdfImport::STATUS_PROCESSING]);

        $absolute = $this->absolutePdfPath($import);
        if (!is_file($absolute)) {
            return $this->fail($import, 'Stored PDF file not found.');
        }

        $extract = $this->extractor->extract($absolute, $import->original_filename, $ocrText);
        if (!$extract['ok'] || empty($extract['data']) || !$this->extractor->hasMinimumFields($this->normalizeExtractedData($extract['data'] ?? []))) {
            return $this->fail($import, $extract['error'] ?? 'CNIC and complainant name not found in PDF.');
        }

        $data = $this->normalizeExtractedData($extract['data']);

        $import->update([
            'extracted_data' => $data,
            'page_count'     => $extract['page_count'],
            'used_ocr'       => $extract['used_ocr'],
            'inquiry_ref'    => $data['inquiry_no'] ?? $import->inquiry_ref,
            'status'         => ComplaintPdfImport::STATUS_EXTRACTED,
            'processed_at'   => now(),
        ]);

        return $import->fresh();
    }

    public function applyToSystem(ComplaintPdfImport $import, User $user, bool $createIfMissing = true, bool $copyAttachment = true): ComplaintPdfImport
    {
        if (!$import->extracted_data) {
            $import = $this->process($import);
        }

        if ($import->status === ComplaintPdfImport::STATUS_FAILED) {
            return $import;
        }

        $data = $this->normalizeExtractedData($import->extracted_data ?? []);
        if (empty($data)) {
            return $this->fail($import, 'Nothing to import.');
        }

        try {
            $attachmentPath = null;
            $hierarchy = $this->resolveHierarchy($import->stored_path, $data);

            $result = DB::transaction(function () use ($import, $data, $user, $createIfMissing, $copyAttachment, $hierarchy, &$attachmentPath) {
                $complaint = $this->findOrCreateComplaint($data, $user, $createIfMissing, $import, $hierarchy);
                if (!$complaint) {
                    throw new \RuntimeException('Complaint not found and create_if_missing is disabled.');
                }

                $attachmentPath = $copyAttachment ? $this->attachPdfToComplaint($complaint, $import) : null;

                $report = $this->upsertVerificationReport($complaint, $data, $user, $import, $attachmentPath, $hierarchy);

                $this->linkEnquiryInquiryNo($data, $report, $hierarchy);

                return [
                    'complaint_id'           => $complaint->id,
                    'verification_report_id' => $report?->id,
                    'tracking_no'            => $complaint->tracking_no,
                    'inquiry_no'             => $data['inquiry_no'] ?? null,
                    'cnic'                   => $complaint->cnic,
                    'complainant_name'       => $complaint->complainant_name,
                    'circle_id'              => $complaint->circle_id,
                    'io_id'                  => $hierarchy['io_user_id'] ?? null,
                    'attachment'             => $attachmentPath,
                ];
            });

            $import->update([
                'status'                 => ComplaintPdfImport::STATUS_IMPORTED,
                'complaint_id'           => $result['complaint_id'],
                'verification_report_id' => $result['verification_report_id'],
                'import_result'          => $result,
                'processed_at'           => now(),
                'error_message'          => null,
            ]);
        } catch (\Throwable $e) {
            return $this->fail($import, $e->getMessage());
        }

        return $import->fresh(['complaint', 'verificationReport']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeExtractedData(array $data): array
    {
        if (!empty($data['victim_cnic'])) {
            $data['victim_cnic'] = $this->extractor->normalizeCnic((string) $data['victim_cnic']);
        }

        if (!empty($data['victim_phone'])) {
            $data['victim_phone'] = $this->formatPhoneDisplay((string) $data['victim_phone']);
        }

        if (!empty($data['victim_name']) && !empty($data['victim_father_name'])) {
            $data['complainant_full_name'] = trim($data['victim_name'] . ' S/O ' . $data['victim_father_name']);
        } else {
            $data['complainant_full_name'] = $data['victim_name'] ?? null;
        }

        if (empty($data['recommendation']) && !empty($data['recommendation_short'])) {
            $lower = strtolower((string) $data['recommendation_short']);
            if (str_contains($lower, 'register enq')) {
                $data['recommendation'] = 'enquiry_registration';
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{circle_id: ?int, io_user_id: ?int, io_name: ?string, moharrar_id: ?int, circle_name: ?string}  $hierarchy
     */
    private function findOrCreateComplaint(array $data, User $user, bool $createIfMissing, ComplaintPdfImport $import, array $hierarchy = []): ?Complaint
    {
        $complaint = null;

        if (!empty($data['tracking_no'])) {
            $complaint = Complaint::where('tracking_no', $data['tracking_no'])->first();
        }

        if (!$complaint && !empty($data['inquiry_no'])) {
            $complaint = Complaint::where('diary_no', $data['inquiry_no'])->latest('id')->first();
        }

        if (!$complaint && !empty($data['inquiry_no'])) {
            $report = VerificationReport::where('inquiry_no', $data['inquiry_no'])->first();
            if ($report?->complaint_id) {
                $complaint = Complaint::find($report->complaint_id);
            }
        }

        if (!$complaint && !empty($data['victim_cnic'])) {
            $complaint = Complaint::where('cnic', $data['victim_cnic'])->latest('id')->first();
        }

        if ($complaint) {
            $complaint->update($this->buildComplaintPayload($data, $user, $import, $complaint, $hierarchy));

            return $complaint->fresh();
        }

        if (!$createIfMissing) {
            return null;
        }

        return Complaint::create($this->buildComplaintPayload($data, $user, $import, null, $hierarchy));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{circle_id: ?int, io_user_id: ?int, io_name: ?string, moharrar_id: ?int, circle_name: ?string}  $hierarchy
     * @return array<string, mixed>
     */
    private function buildComplaintPayload(array $data, User $user, ComplaintPdfImport $import, ?Complaint $existing = null, array $hierarchy = []): array
    {
        $fullName = $data['complainant_full_name']
            ?? $data['victim_name']
            ?? $existing?->complainant_name;

        if (!$fullName) {
            throw new \RuntimeException('Complainant name not found in PDF.');
        }

        if (empty($data['victim_cnic']) && !$existing?->cnic) {
            throw new \RuntimeException('CNIC not found in PDF.');
        }

        $address = $data['victim_address'] ?? $existing?->address ?? 'Imported from PDF';
        $postAddress = $data['victim_permanent_address'] ?? $data['victim_address'] ?? $existing?->post_address ?? $address;
        $description = trim(implode("\n\n", array_filter([
            $data['crime_category'] ?? null,
            $data['crime_description'] ?? null,
            !empty($data['accused_name']) ? 'Accused: ' . $data['accused_name'] : null,
            !empty($data['city']) ? 'City: ' . $data['city'] : null,
            !empty($data['amount_involved']) ? 'Amount: ' . $data['amount_involved'] : null,
            !empty($data['recommendation_short']) ? 'Recommendation: ' . $data['recommendation_short'] : null,
            !empty($data['recommendation_full']) ? $data['recommendation_full'] : null,
            !empty($data['reporting_officer']) ? 'Reporting Officer: ' . $data['reporting_officer'] : null,
        ]))) ?: ($existing?->description ?? 'Imported verification report');

        $circleId = $hierarchy['circle_id'] ?? $existing?->circle_id ?? $user->circle_id;
        $operatorId = $hierarchy['moharrar_id'] ?? $existing?->operator_id ?? $user->id;

        $payload = [
            'complainant_name'     => $fullName,
            'cnic'                 => $data['victim_cnic'] ?? $existing?->cnic,
            'contact_no'           => $this->contactDigits($data['victim_phone'] ?? $existing?->contact_no ?? '3000000000'),
            'contact_country_code' => '+92',
            'email'                => $data['victim_email'] ?? $existing?->email,
            'nationality'          => 'Pakistani',
            'address'              => $address,
            'post_address'         => $postAddress,
            'profession'           => $data['victim_occupation'] ?? $existing?->profession,
            'report_date'          => $data['verification_date'] ?? $data['assignment_date'] ?? $existing?->report_date ?? now()->toDateString(),
            'diary_no'             => $data['inquiry_no'] ?? $data['tracking_no'] ?? $existing?->diary_no ?? pathinfo($import->original_filename, PATHINFO_FILENAME),
            'received_via'         => $existing?->received_via ?? 'Postal Service',
            'received_from'        => $existing?->received_from ?? 'General Public',
            'cmu'                  => $existing?->cmu ?? 'CCRC',
            'priority_type'        => $existing?->priority_type ?? 'normal',
            'offence_type'         => $data['crime_category'] ?? $existing?->offence_type ?? 'Cyber Crime',
            'amount_involved'      => $data['amount_involved'] ?? $existing?->amount_involved,
            'occurrence_date'      => $data['assignment_date'] ?? $data['verification_date'] ?? $existing?->occurrence_date ?? now()->toDateString(),
            'description'          => $description,
            'operator_name'        => $existing?->operator_name ?? $user->name,
            'operator_designation' => $existing?->operator_designation ?? ($user->designation ?? ($user->roles->first()?->name ?? 'Operator')),
            'entry_time'           => $existing?->entry_time ?? now(),
            'operator_remarks'     => 'Auto-imported from PDF: ' . $import->original_filename,
            'scrutiny_result'      => 'complete',
            'status'               => 'complete',
            'source'               => 'pdf_import',
            'user_id'              => $existing?->user_id ?? $user->id,
            'operator_id'          => $operatorId,
            'circle_id'            => $circleId,
        ];

        $accusedList = $data['accused'] ?? $data['initial_accused'] ?? [];
        if (!empty($accusedList) && is_array($accusedList)) {
            $payload['initial_accused'] = $accusedList;
        }

        if (!empty($data['tracking_no'])) {
            $payload['tracking_no'] = $data['tracking_no'];
        } elseif (empty($existing?->tracking_no) && !empty($data['inquiry_no'])) {
            $payload['tracking_no'] = null;
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{circle_id: ?int, io_user_id: ?int, io_name: ?string, moharrar_id: ?int, circle_name: ?string}  $hierarchy
     */
    private function upsertVerificationReport(
        Complaint $complaint,
        array $data,
        User $user,
        ComplaintPdfImport $import,
        ?string $attachmentPath,
        array $hierarchy = []
    ): ?VerificationReport {
        $trackingNo = $data['tracking_no'] ?? $complaint->tracking_no;
        if (!$trackingNo && empty($data['victim_name']) && empty($complaint->complainant_name)) {
            return null;
        }

        $fullName = $data['complainant_full_name'] ?? $data['victim_name'] ?? $complaint->complainant_name;

        $accused = $data['accused'] ?? [];
        if (empty($accused) && (!empty($data['accused_name']) || !empty($data['accused_cnic']) || !empty($data['accused_phone']))) {
            $accused[] = array_filter([
                'name'  => $data['accused_name'] ?? null,
                'cnic'  => $data['accused_cnic'] ?? null,
                'phone' => $data['accused_phone'] ?? null,
            ]);
        }

        $reportData = [
            'complaint_id'         => $complaint->id,
            'tracking_no'          => $trackingNo ?? ('IMPORT-' . $complaint->id),
            'assignment_date'      => $data['assignment_date'] ?? null,
            'verification_date'    => $data['verification_date'] ?? null,
            'victim_name'          => $fullName,
            'victim_father_name'   => $data['victim_father_name'] ?? null,
            'victim_occupation'    => $data['victim_occupation'] ?? $complaint->profession,
            'victim_gender'        => $data['victim_gender'] ?? null,
            'victim_cnic'          => $data['victim_cnic'] ?? $complaint->cnic,
            'victim_country_code'  => '+92',
            'victim_phone'         => $data['victim_phone'] ?? $this->formatPhoneDisplay($complaint->contact_no),
            'victim_email'         => $data['victim_email'] ?? null,
            'crime_category'       => $data['crime_category'] ?? $complaint->offence_type,
            'crime_description'    => $data['crime_description'] ?? $complaint->description,
            'city'                 => $data['city'] ?? ($hierarchy['circle_name'] ?? 'Unknown'),
            'accused_known'        => !empty($accused),
            'accused'              => $accused ?: null,
            'recommendation'       => $data['recommendation'] ?? 'enquiry_registration',
            'recommendation_short' => is_string($data['recommendation_short'] ?? null)
                ? Str::limit($data['recommendation_short'], 800)
                : null,
            'recommendation_full'  => $data['recommendation_full'] ?? null,
            'inquiry_no'           => $data['inquiry_no'] ?? null,
            'created_by'           => $hierarchy['moharrar_id'] ?? $user->id,
        ];

        $existing = VerificationReport::where(function ($q) use ($complaint, $data) {
            $q->where('complaint_id', $complaint->id);
            if (!empty($data['inquiry_no'])) {
                $q->orWhere('inquiry_no', $data['inquiry_no']);
            }
        })->latest('id')->first();

        if ($attachmentPath) {
            $evidence = $existing?->evidence ?? [];
            $already = collect($evidence)->contains(fn ($e) => ($e['file'] ?? '') === $attachmentPath || ($e['original_name'] ?? '') === $import->original_filename);
            if (!$already) {
                $evidence[] = [
                    'file'          => $attachmentPath,
                    'original_name' => $import->original_filename,
                    'description'   => 'Verification report PDF (auto-import)',
                ];
            }
            $reportData['evidence'] = $evidence;
        }

        $report = null;
        if ($existing) {
            $existing->update(array_filter($reportData, fn ($v) => $v !== null && $v !== ''));
            $report = $existing->fresh();
        } else {
            $report = VerificationReport::create(array_filter($reportData, fn ($v) => $v !== null && $v !== ''));
        }

        // Link Verification record to IO if present
        if (!empty($hierarchy['io_user_id'])) {
            \App\Models\Verification::updateOrCreate(
                ['complaint_id' => $complaint->id],
                [
                    'verification_officer_id' => $hierarchy['io_user_id'],
                    'priority_type'           => $complaint->priority_type ?? 'normal',
                    'assigned_by'             => $user->id,
                    'assigned_at'             => now(),
                    'status'                  => 'assigned',
                ]
            );
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{circle_id: ?int, io_user_id: ?int, io_name: ?string, moharrar_id: ?int, circle_name: ?string}  $hierarchy
     */
    private function linkEnquiryInquiryNo(array $data, ?VerificationReport $report, array $hierarchy = []): void
    {
        if (!$report || empty($data['inquiry_no'])) {
            return;
        }

        $enquiry = Enquiry::where('complaint_id', $report->complaint_id)->first();
        if ($enquiry) {
            $enquiry->update(array_filter([
                'enquiry_number'     => $data['inquiry_no'],
                'enquiry_officer_id' => $hierarchy['io_user_id'] ?? $enquiry->enquiry_officer_id,
                'circle_id'          => $hierarchy['circle_id'] ?? $enquiry->circle_id,
            ]));
        } elseif (!empty($hierarchy['io_user_id'])) {
            Enquiry::create([
                'complaint_id'       => $report->complaint_id,
                'enquiry_number'     => $data['inquiry_no'],
                'enquiry_officer_id' => $hierarchy['io_user_id'],
                'circle_id'          => $hierarchy['circle_id'] ?? $report->complaint->circle_id,
                'status'             => 'assigned',
                'tracking_no'        => $report->complaint->tracking_no ?? $data['inquiry_no'],
                'complainant_name'   => $report->victim_name,
                'registration_date'  => $report->assignment_date ?? now(),
            ]);
        }
    }

    /**
     * Resolve Circle, IO (Enquiry Officer), and Moharar from directory path and text.
     *
     * @param  array<string, mixed>  $extractedData
     * @return array{circle_id: ?int, io_user_id: ?int, io_name: ?string, moharrar_id: ?int, circle_name: ?string}
     */
    public function resolveHierarchy(string $pdfPath, array $extractedData = []): array
    {
        $hierarchy = [
            'circle_id'    => null,
            'circle_name'  => null,
            'io_user_id'   => null,
            'io_name'      => null,
            'moharrar_id'  => null,
        ];

        $normalized = str_replace('\\', '/', $pdfPath);
        $parts = array_values(array_filter(explode('/', $normalized)));

        static $circlesCache = null;
        static $iosCache = null;
        static $usersCache = null;

        if ($circlesCache === null) {
            $circlesCache = \App\Models\Circle::all();
            $iosCache = \App\Models\InvestigationOfficer::with('user')->get();
            $usersCache = \App\Models\User::all();
        }

        // 1. Check directory path segments
        foreach ($parts as $segment) {
            $segLower = strtolower(trim($segment));
            if ($segLower === '' || str_ends_with($segLower, '.pdf')) {
                continue;
            }

            // Match Circle
            if (!$hierarchy['circle_id']) {
                foreach ($circlesCache as $c) {
                    $cName = strtolower($c->name);
                    if ($cName !== '' && (str_contains($segLower, $cName) || str_contains($cName, $segLower))) {
                        $hierarchy['circle_id'] = $c->id;
                        $hierarchy['circle_name'] = $c->name;
                        break;
                    }
                }
            }

            // Match IO
            if (!$hierarchy['io_user_id']) {
                foreach ($iosCache as $io) {
                    $ioName = strtolower(trim($io->name));
                    if (strlen($ioName) > 3 && str_contains($segLower, $ioName)) {
                        $hierarchy['io_user_id'] = $io->user_id;
                        $hierarchy['io_name'] = $io->name;
                        break;
                    }
                }
            }

            // Match Moharar / User
            if (!$hierarchy['moharrar_id']) {
                foreach ($usersCache as $u) {
                    $uName = strtolower(trim($u->name));
                    if (strlen($uName) > 3 && str_contains($segLower, $uName) && in_array($u->role, ['moharrar', 'reader_branch', 'operator'], true)) {
                        $hierarchy['moharrar_id'] = $u->id;
                        break;
                    }
                }
            }
        }

        // 2. Fallback to document text (Reporting Officer, City, etc.)
        if (!$hierarchy['circle_id'] && !empty($extractedData['city'])) {
            $cityLower = strtolower($extractedData['city']);
            foreach ($circlesCache as $c) {
                if (str_contains($cityLower, strtolower($c->name))) {
                    $hierarchy['circle_id'] = $c->id;
                    $hierarchy['circle_name'] = $c->name;
                    break;
                }
            }
        }

        if (!$hierarchy['io_user_id'] && !empty($extractedData['reporting_officer'])) {
            $repOfficerLower = strtolower($extractedData['reporting_officer']);
            foreach ($iosCache as $io) {
                if (str_contains($repOfficerLower, strtolower(trim($io->name)))) {
                    $hierarchy['io_user_id'] = $io->user_id;
                    $hierarchy['io_name'] = $io->name;
                    break;
                }
            }
            if (!$hierarchy['io_user_id']) {
                foreach ($usersCache as $u) {
                    if (str_contains($repOfficerLower, strtolower(trim($u->name)))) {
                        $hierarchy['io_user_id'] = $u->id;
                        $hierarchy['io_name'] = $u->name;
                        break;
                    }
                }
            }
        }

        return $hierarchy;
    }

    private function attachPdfToComplaint(Complaint $complaint, ComplaintPdfImport $import): string
    {
        $source = $this->absolutePdfPath($import);
        $destDir = public_path('uploads/complaints');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if ($complaint->attachment && is_file(public_path($complaint->attachment))) {
            @unlink(public_path($complaint->attachment));
        }

        $safeBase = Str::slug(pathinfo($import->original_filename, PATHINFO_FILENAME)) ?: 'import';
        $destName = $safeBase . '-' . Str::random(8) . '.pdf';
        $destRel = 'uploads/complaints/' . $destName;
        copy($source, public_path($destRel));
        $complaint->update(['attachment' => $destRel]);

        return $destRel;
    }

    private function contactDigits(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with($digits, '92') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return substr($digits, 0, 10) ?: '3000000000';
    }

    private function formatPhoneDisplay(string $phone): string
    {
        $digits = $this->contactDigits($phone);
        if (strlen($digits) === 10) {
            return '0' . $digits;
        }

        return $phone;
    }

    private function fail(ComplaintPdfImport $import, string $message): ComplaintPdfImport
    {
        $import->update([
            'status'        => ComplaintPdfImport::STATUS_FAILED,
            'error_message' => $message,
            'processed_at'  => now(),
        ]);

        return $import->fresh();
    }
}

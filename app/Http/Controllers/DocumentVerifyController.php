<?php

namespace App\Http\Controllers;

use App\Models\CaseActivity;
use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\DoLetter;
use App\Models\DsrReport;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Models\EnquiryNotice;
use App\Models\ForensicRequest;
use App\Services\QrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentVerifyController extends Controller
{
    private const LABELS = [
        'complaint'         => 'Complaint Receipt',
        'complaint_report'  => 'Complaint Report',
        'enquiry'           => 'Enquiry',
        'notice'            => 'Summon / Notice',
        'cfr'               => 'Confidential Final Report',
        'forensic'          => 'Forensic Request',
        'custody'           => 'Chain of Custody',
        'raid'              => 'Raid Permission',
        'warrant'           => 'Search Warrant',
        'arrest'            => 'Arrest Warrant',
        'diary'             => 'Case Diary',
        'account'           => 'Account Opening Request',
        'scope'             => 'Forensic Scope Letter',
        'dsr_report'        => 'Daily Situation Report (DSR)',
        'do_letter'         => 'D.O. Letter',
    ];

    public function __construct(private readonly QrService $qr)
    {
    }

    /**
     * Public page opened when a document QR is scanned.
     */
    public function show(string $type, string $id, string $token)
    {
        abort_unless($this->qr->checkToken($type, $id, $token), 404);
        abort_unless(isset(self::LABELS[$type]), 404);

        $payload = $this->payload($type, $id);

        return view('verify.document', $payload);
    }

    /**
     * Authenticated helper so frontend prints encode the same signed payload.
     */
    public function qr(Request $request, string $type, string $id): JsonResponse
    {
        abort_unless(isset(self::LABELS[$type]), 404);

        // Scope to documents the caller may see (IDOR fix). Only enforced for the
        // types that map cleanly to a circle-scoped record; other document types
        // remain gated by their own module routes.
        abort_unless($this->callerCanSeeDocument($request->user(), $type, (int) $id), 403, 'Unauthorized for this document.');

        try {
            $payload = $this->payload($type, $id);
            $fields = ['Type' => $payload['title']];
            foreach ($payload['rows'] as $row) {
                $fields[$row['k']] = $row['v'];
            }

            $url = $this->qr->verifyUrl($type, $id);

            return response()->json([
                'url'     => $url,
                'payload' => $url,
                'caption' => $fields['FIR No'] ?? ($fields['Lab File No'] ?? ($fields['Request No'] ?? ($fields['Enquiry No'] ?? ($fields['Complaint No'] ?? $payload['title'])))),
                'title'   => $payload['title'],
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'url'     => $this->qr->verifyUrl($type, $id),
                'payload' => $this->qr->verifyUrl($type, $id),
                'caption' => self::LABELS[$type] ?? $type,
                'title'   => self::LABELS[$type] ?? $type,
            ]);
        }
    }

    /**
     * May this user obtain the signed link / details for this document?
     * Enforced for circle-scoped types; unknown types fall through to true
     * (they are already restricted by their own module routes).
     */
    private function callerCanSeeDocument(?\App\Models\User $user, string $type, int $id): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->seesAllData()) {
            return true;
        }

        switch ($type) {
            case 'complaint':
            case 'complaint_report':
                return Complaint::visibleTo($user)->whereKey($id)->exists();
            case 'enquiry':
            case 'cfr':
            case 'account':
            case 'diary':
            case 'raid':
            case 'warrant':
            case 'arrest':
                return Enquiry::visibleTo($user)->whereKey($id)->exists();
            case 'notice':
                $notice = EnquiryNotice::find($id);
                return $notice && Enquiry::visibleTo($user)->whereKey($notice->enquiry_id)->exists();
            default:
                // forensic / custody / scope / dsr_report / do_letter: governed by
                // their own role-gated routes; do not broaden or break them here.
                return true;
        }
    }

    /**
     * @return array{title:string,rows:list<array{k:string,v:string}>,devices:list<array>,custody:list<array>,notes:list<array{k:string,v:string}>}
     */
    private function payload(string $type, string $id): array
    {
        $title = self::LABELS[$type];
        $rows = [];
        $devices = [];
        $custody = [];
        $notes = [];

        switch ($type) {
            case 'complaint':
            case 'complaint_report':
                $rows = $this->complaintRows((int) $id);
                break;
            case 'enquiry':
            case 'cfr':
            case 'account':
                $rows = $this->enquiryRows((int) $id);
                break;
            case 'scope':
                [$rows, $devices, $notes] = $this->scopeDetails((int) $id);
                break;
            case 'raid':
            case 'warrant':
            case 'arrest':
                [$rows, $devices, $notes] = $this->warrantDetails((int) $id, $type);
                break;
            case 'diary':
                $rows = $this->diaryRows((int) $id);
                break;
            case 'notice':
                $rows = $this->noticeRows((int) $id);
                break;
            case 'forensic':
            case 'custody':
                [$rows, $devices, $custody, $notes] = $this->forensicDetails((int) $id);
                break;
            case 'dsr_report':
                $rows = $this->dsrReportRows((int) $id);
                break;
            case 'do_letter':
                $rows = $this->doLetterRows((int) $id);
                break;
            default:
                abort(404);
        }

        return compact('title', 'rows', 'devices', 'custody', 'notes');
    }

    private function complaintRows(int $id): array
    {
        $c = Complaint::with('circle')->findOrFail($id);

        return [
            ['k' => 'Complaint No', 'v' => $c->tracking_no ?: ($c->slip_number ?: ('#' . $c->id))],
            ['k' => 'Complainant', 'v' => $c->complainant_name ?: '—'],
            ['k' => 'CNIC', 'v' => $this->maskCnic($c->cnic)],
            ['k' => 'Circle', 'v' => $c->circle?->name ?: '—'],
            ['k' => 'Offence', 'v' => $c->offence_type ?: '—'],
            ['k' => 'Report Date', 'v' => $c->report_date ? \Carbon\Carbon::parse($c->report_date)->format('d/m/Y') : '—'],
            ['k' => 'Status', 'v' => $this->statusLabel($c->final_status ?: $c->status)],
        ];
    }

    private function enquiryRows(int $id): array
    {
        $e = Enquiry::with(['complaint.circle', 'officer'])->findOrFail($id);
        $direct = is_array($e->direct_info) ? $e->direct_info : [];

        return [
            ['k' => 'Enquiry No', 'v' => $e->enquiry_number ?: ('ENQ-' . $e->id)],
            ['k' => 'Complaint No', 'v' => $e->complaint?->tracking_no ?: ($direct['reference_no'] ?? '—')],
            ['k' => 'Complainant', 'v' => $e->complaint?->complainant_name ?: ($direct['complainant_name'] ?? '—')],
            ['k' => 'Circle', 'v' => $e->complaint?->circle?->name ?: ($direct['circle_name'] ?? '—')],
            ['k' => 'Officer', 'v' => $e->officer?->name ?: '—'],
            ['k' => 'Status', 'v' => $this->statusLabel($e->status)],
            ['k' => 'Registered', 'v' => $e->reg_date ? \Carbon\Carbon::parse($e->reg_date)->format('d/m/Y') : ($e->created_at?->format('d/m/Y') ?: '—')],
        ];
    }

    /**
     * Warrant / raid / arrest verification — prefers activity ID (matches printed QR).
     *
     * @return array{0:list<array{k:string,v:string}>,1:list<array>,2:list<array{k:string,v:string}>}
     */
    private function warrantDetails(int $id, string $docType): array
    {
        $activity = EnquiryActivity::with([
            'enquiry.complaint.circle',
            'enquiry.officer',
            'enquiry.accusedPersons',
            'enquiry.caseFile',
        ])->find($id);

        if ($activity?->enquiry) {
            return $this->buildWarrantVerifyPayload($activity->enquiry, $activity, $docType);
        }

        $caseActivity = CaseActivity::with([
            'caseFile.enquiry.complaint.circle',
            'caseFile.enquiry.officer',
            'caseFile.enquiry.accusedPersons',
            'caseFile.investigationOfficer',
        ])->find($id);

        if ($caseActivity?->caseFile) {
            $caseFile = $caseActivity->caseFile;
            $enquiry = $caseFile->enquiry;
            if (!$enquiry) {
                $enquiry = new Enquiry([
                    'enquiry_number' => $caseFile->fir_no,
                    'direct_info'    => $caseFile->direct_info,
                ]);
                $enquiry->setRelation('caseFile', $caseFile);
                $enquiry->setRelation('officer', $caseFile->investigationOfficer);
                $enquiry->setRelation('complaint', null);
                $enquiry->setRelation('accusedPersons', collect());
            } else {
                $enquiry->setRelation('caseFile', $caseFile);
            }

            return $this->buildWarrantVerifyPayload($enquiry, $caseActivity, $docType);
        }

        return $this->scopeDetails($id);
    }

    /**
     * @param  EnquiryActivity|CaseActivity  $activity
     * @return array{0:list<array{k:string,v:string}>,1:list<array>,2:list<array{k:string,v:string}>}
     */
    private function buildWarrantVerifyPayload(Enquiry $enquiry, EnquiryActivity|CaseActivity $activity, string $docType): array
    {
        $meta = is_array($activity->meta) ? $activity->meta : [];
        $label = self::LABELS[$docType] ?? ucwords(str_replace('_', ' ', $docType));

        $activityType = (string) ($activity->type ?? '');
        if ($activityType === 'search_seize') {
            $label = 'Search Warrant';
        } elseif ($activityType === 'raid') {
            $label = 'Raid Permission';
        } elseif ($activityType === 'arrest_warrant') {
            $label = 'Arrest Warrant';
        }

        $enquiryId = (int) ($enquiry->id ?: ($activity instanceof EnquiryActivity ? $activity->enquiry_id : 0));
        $baseRows = $enquiryId > 0 ? $this->enquiryRows($enquiryId) : $this->enquiryRowsFromModel($enquiry);

        $subject = trim((string) ($meta['subject'] ?? ''));
        $kota = trim((string) ($meta['kota'] ?? ''));
        $against = trim((string) ($meta['against_whom'] ?? ''));
        $scheduled = trim((string) ($meta['scheduled_at'] ?? ''));
        $description = trim((string) ($activity->description ?? ''));

        $docRows = [
            ['k' => 'Document', 'v' => $label],
            ['k' => 'Subject', 'v' => $subject ?: '—'],
            ['k' => 'Location / Kota', 'v' => $kota ?: '—'],
            ['k' => 'Against', 'v' => $against ?: '—'],
            ['k' => 'Scheduled', 'v' => $this->formatDateTime($scheduled)],
            ['k' => 'Activity Date', 'v' => $activity->activity_date ? $activity->activity_date->format('d/m/Y') : '—'],
        ];

        if ($enquiry->caseFile?->fir_no) {
            array_splice($docRows, 1, 0, [['k' => 'FIR No', 'v' => (string) $enquiry->caseFile->fir_no]]);
        }

        $rows = array_merge($docRows, $baseRows);
        $devices = $this->devicesFromActivityMeta($meta);
        $notes = [];

        if ($description !== '' && !in_array($description, ['Search Warrant', 'Raid Permission', 'Arrest Warrant', 'Search & Seize'], true)) {
            $notes[] = ['k' => 'Brief Facts', 'v' => $description];
        }

        $scopeText = trim((string) ($meta['analysis_scope'] ?? ''));
        if ($scopeText !== '') {
            $notes[] = ['k' => 'Scope of Analysis', 'v' => $scopeText];
        }

        return [$rows, $devices, $notes];
    }

    private function enquiryRowsFromModel(Enquiry $e): array
    {
        if ($e->id) {
            return $this->enquiryRows((int) $e->id);
        }

        $direct = is_array($e->direct_info) ? $e->direct_info : [];

        return [
            ['k' => 'Enquiry No', 'v' => $e->enquiry_number ?: '—'],
            ['k' => 'Complaint No', 'v' => $e->complaint?->tracking_no ?: ($direct['reference_no'] ?? '—')],
            ['k' => 'Complainant', 'v' => $e->complaint?->complainant_name ?: ($direct['complainant_name'] ?? '—')],
            ['k' => 'Circle', 'v' => $e->complaint?->circle?->name ?: ($direct['circle_name'] ?? '—')],
            ['k' => 'Officer', 'v' => $e->officer?->name ?: '—'],
            ['k' => 'Status', 'v' => $this->statusLabel($e->status)],
            ['k' => 'Registered', 'v' => $e->reg_date ? \Carbon\Carbon::parse($e->reg_date)->format('d/m/Y') : ($e->created_at?->format('d/m/Y') ?: '—')],
        ];
    }

    private function devicesFromActivityMeta(array $meta): array
    {
        $devices = [];
        $items = $meta['seize_items'] ?? [];
        if (!is_array($items)) {
            return [];
        }
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $devices[] = $this->normalizeDevice($it);
        }

        return $devices;
    }

    private function formatDateTime(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '—';
        }
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y h:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @return array{0:list<array{k:string,v:string}>,1:list<array>,2:list<array{k:string,v:string}>}
     */
    private function scopeDetails(int $id): array
    {
        $e = Enquiry::with(['complaint.circle', 'officer', 'activities', 'accusedPersons'])->findOrFail($id);
        $rows = $this->enquiryRows($id);
        $devices = $this->enquiryDevices($e);
        $notes = [];

        $scopeText = '';
        foreach ($e->activities as $act) {
            if (in_array($act->type, ['seizures', 'search_seize'], true)) {
                $meta = is_array($act->meta) ? $act->meta : [];
                $chunk = trim((string) ($meta['analysis_scope'] ?? $act->description ?? ''));
                if ($chunk !== '') {
                    $scopeText .= ($scopeText ? "\n\n" : '') . $chunk;
                }
            }
        }

        $fr = ForensicRequest::where('enquiry_id', $e->id)->latest('id')->first();
        if ($fr && !empty($fr->note)) {
            $notes[] = ['k' => 'Forwarding Note', 'v' => (string) $fr->note];
        }
        if ($scopeText !== '') {
            $notes[] = ['k' => 'Scope of Analysis', 'v' => $scopeText];
        }

        return [$rows, $devices, $notes];
    }

    /**
     * @return array{0:list<array{k:string,v:string}>,1:list<array>,2:list<array>,3:list<array{k:string,v:string}>}
     */
    private function forensicDetails(int $id): array
    {
        $f = ForensicRequest::with([
            'items',
            'enquiry.complaint.circle',
            'enquiry.officer',
            'enquiry.accusedPersons',
            'submitter',
            'assignee',
            'adReviewer',
            'handedTo',
        ])->findOrFail($id);

        $e = $f->enquiry;
        $direct = is_array($e?->direct_info) ? $e->direct_info : [];

        $rows = [
            ['k' => 'Lab File No', 'v' => $f->request_no ?: ('FR-' . $f->id)],
            ['k' => 'Report Code', 'v' => $f->report_code ?: '—'],
            ['k' => 'Enquiry No', 'v' => $e?->enquiry_number ?: ($e ? ('ENQ-' . $e->id) : '—')],
            ['k' => 'Complainant', 'v' => $e?->complaint?->complainant_name ?: ($direct['complainant_name'] ?? ($f->external_person_name ?: '—'))],
            ['k' => 'Organization', 'v' => $f->external_organization ?: ($e?->complaint?->circle?->name ?: '—')],
            ['k' => 'Submitted By', 'v' => $f->submitter?->name ?: '—'],
            ['k' => 'Assigned Examiner', 'v' => $f->assignee?->name ?: ($f->adReviewer?->name ?: '—')],
            ['k' => 'Status', 'v' => $this->statusLabel($f->status)],
            ['k' => 'Priority', 'v' => $this->statusLabel($f->priority)],
            ['k' => 'Received', 'v' => $f->created_at?->format('d/m/Y h:i A') ?: '—'],
            ['k' => 'Opened', 'v' => $f->opened_at ? \Carbon\Carbon::parse($f->opened_at)->format('d/m/Y h:i A') : '—'],
            ['k' => 'Report Ready', 'v' => $f->report_ready_at ? \Carbon\Carbon::parse($f->report_ready_at)->format('d/m/Y h:i A') : '—'],
            ['k' => 'Handed Over', 'v' => $f->handed_over_at ? \Carbon\Carbon::parse($f->handed_over_at)->format('d/m/Y h:i A') : '—'],
        ];

        $devices = [];
        foreach ($f->items as $it) {
            $devices[] = $this->normalizeDevice([
                'item_type'         => $it->item_type,
                'make_model'        => $it->make_model,
                'imei'              => $it->imei,
                'imei2'             => $it->imei2,
                'serial_no'         => $it->serial_no,
                'storage_capacity'  => $it->storage_capacity,
                'condition'         => $it->condition,
                'seized_from'       => $it->seized_from,
                'quantity'          => $it->quantity,
                'description'       => $it->description,
            ]);
        }

        if (!$devices && $e) {
            $e->loadMissing(['activities']);
            $devices = $this->enquiryDevices($e);
        }

        $custody = [
            [
                'from'   => $f->submitter?->name ?: 'Enquiry Officer',
                'to'     => $f->adReviewer?->name ?: ($f->assignee?->name ?: 'AD Forensic'),
                'at'     => $f->created_at?->format('d/m/Y h:i A') ?: '—',
                'remark' => 'Received at Forensic Lab',
            ],
        ];
        if ($f->assigned_at) {
            $custody[] = [
                'from'   => $f->adReviewer?->name ?: 'AD Forensic',
                'to'     => $f->assignee?->name ?: 'Forensic Examiner',
                'at'     => \Carbon\Carbon::parse($f->assigned_at)->format('d/m/Y h:i A'),
                'remark' => 'Assigned for examination',
            ];
        }
        if ($f->report_ready_at) {
            $custody[] = [
                'from'   => $f->assignee?->name ?: 'Examiner',
                'to'     => $f->adReviewer?->name ?: 'AD Forensic',
                'at'     => \Carbon\Carbon::parse($f->report_ready_at)->format('d/m/Y h:i A'),
                'remark' => 'Report ready',
            ];
        }
        if ($f->handed_over_at) {
            $custody[] = [
                'from'   => $f->adReviewer?->name ?: 'AD Forensic',
                'to'     => $f->handedTo?->name ?: ($f->submitter?->name ?: 'Enquiry Officer'),
                'at'     => \Carbon\Carbon::parse($f->handed_over_at)->format('d/m/Y h:i A'),
                'remark' => trim((string) ($f->handover_remarks ?: 'Custody handed over')),
            ];
        }

        $notes = [];
        if (!empty($f->note)) {
            $notes[] = ['k' => 'Lab Remarks', 'v' => (string) $f->note];
        }
        if (!empty($f->findings)) {
            $notes[] = ['k' => 'Findings', 'v' => (string) $f->findings];
        }
        if (!empty($f->handover_remarks)) {
            $notes[] = ['k' => 'Handover Remarks', 'v' => (string) $f->handover_remarks];
        }

        return [$rows, $devices, $custody, $notes];
    }

    private function enquiryDevices(Enquiry $enquiry): array
    {
        $devices = [];
        $seen = [];

        foreach ($enquiry->activities ?? [] as $act) {
            $meta = is_array($act->meta) ? $act->meta : [];
            $items = $meta['seize_items'] ?? [];
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $norm = $this->normalizeDevice($it);
                $key = strtolower(trim(($norm['imei'] ?: '') . '|' . ($norm['serial_no'] ?: '') . '|' . ($norm['make_model'] ?: '') . '|' . ($norm['item_type'] ?: '')));
                if ($key === '|||' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $devices[] = $norm;
            }
        }

        $requests = ForensicRequest::with('items')->where('enquiry_id', $enquiry->id)->get();
        foreach ($requests as $fr) {
            foreach ($fr->items as $it) {
                $norm = $this->normalizeDevice([
                    'item_type'         => $it->item_type,
                    'make_model'        => $it->make_model,
                    'imei'              => $it->imei,
                    'imei2'             => $it->imei2,
                    'serial_no'         => $it->serial_no,
                    'storage_capacity'  => $it->storage_capacity,
                    'condition'         => $it->condition,
                    'seized_from'       => $it->seized_from,
                    'quantity'          => $it->quantity,
                    'description'       => $it->description,
                ]);
                $key = strtolower(trim(($norm['imei'] ?: '') . '|' . ($norm['serial_no'] ?: '') . '|' . ($norm['make_model'] ?: '') . '|' . ($norm['item_type'] ?: '')));
                if ($key === '|||' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $devices[] = $norm;
            }
        }

        return $devices;
    }

    private function normalizeDevice(array $it): array
    {
        return [
            'item_type'        => (string) ($it['item_type'] ?? $it['type'] ?? 'Device'),
            'make_model'       => (string) ($it['make_model'] ?? $it['model'] ?? ''),
            'imei'             => (string) ($it['imei'] ?? ''),
            'imei2'            => (string) ($it['imei2'] ?? ''),
            'serial_no'        => (string) ($it['serial_no'] ?? ''),
            'storage_capacity' => (string) ($it['storage_capacity'] ?? ''),
            'condition'        => (string) ($it['condition'] ?? ''),
            'seized_from'      => (string) ($it['seized_from'] ?? $it['owner'] ?? ''),
            'quantity'         => (string) ($it['quantity'] ?? '1'),
            'description'      => (string) ($it['description'] ?? ''),
        ];
    }

    private function diaryRows(int $id): array
    {
        $activity = EnquiryActivity::with('enquiry.complaint.circle')->find($id);
        if ($activity?->enquiry) {
            $rows = $this->enquiryRows((int) $activity->enquiry_id);
            array_unshift($rows, ['k' => 'Diary No', 'v' => $activity->diary_no ?: ('D-' . $activity->id)]);
            $rows[] = ['k' => 'Diary Date', 'v' => $activity->activity_date ? $activity->activity_date->format('d/m/Y') : '—'];

            return $rows;
        }

        return $this->enquiryRows($id);
    }

    private function noticeRows(int $id): array
    {
        $n = EnquiryNotice::with('enquiry.complaint.circle')->findOrFail($id);

        return [
            ['k' => 'Summon No', 'v' => $n->notice_number ?: ('N-' . $n->id)],
            ['k' => 'Receiver', 'v' => $n->receiver_name ?: '—'],
            ['k' => 'Enquiry No', 'v' => $n->enquiry?->enquiry_number ?: ('#' . $n->enquiry_id)],
            ['k' => 'Complaint No', 'v' => $n->enquiry?->complaint?->tracking_no ?: '—'],
            ['k' => 'Circle', 'v' => $n->enquiry?->complaint?->circle?->name ?: '—'],
            ['k' => 'Status', 'v' => $this->statusLabel($n->status)],
        ];
    }

    private function dsrReportRows(int $id): array
    {
        $report = DsrReport::with(['circle', 'compiler'])->findOrFail($id);

        return [
            ['k' => 'Document', 'v' => 'Daily Situation Report (DSR)'],
            ['k' => 'DSR Ref', 'v' => 'DSR-' . $report->id],
            ['k' => 'Unit', 'v' => $report->unit_name ?: '—'],
            ['k' => 'Circle', 'v' => $report->circle?->name ?: '—'],
            ['k' => 'Report Date', 'v' => $report->report_date?->format('d/m/Y') ?: '—'],
            ['k' => 'Status', 'v' => $this->statusLabel($report->status)],
            ['k' => 'Compiled By', 'v' => $report->compiler?->name ?: '—'],
            ['k' => 'Compiled At', 'v' => $report->updated_at?->format('d/m/Y h:i A') ?: '—'],
        ];
    }

    private function doLetterRows(int $id): array
    {
        $letter = DoLetter::with(['circle', 'compiler'])->findOrFail($id);

        return [
            ['k' => 'Document', 'v' => 'D.O. Letter'],
            ['k' => 'Letter Ref', 'v' => 'DO-' . $letter->id],
            ['k' => 'Month', 'v' => $letter->month_label ?: '—'],
            ['k' => 'Circle', 'v' => $letter->circle?->name ?: '—'],
            ['k' => 'Status', 'v' => $this->statusLabel($letter->status)],
            ['k' => 'Compiled By', 'v' => $letter->compiler?->name ?: '—'],
            ['k' => 'Compiled At', 'v' => $letter->updated_at?->format('d/m/Y h:i A') ?: '—'],
        ];
    }

    private function maskCnic(?string $cnic): string
    {
        if (!$cnic) {
            return '—';
        }
        $digits = preg_replace('/\D/', '', $cnic);
        if (strlen($digits) >= 10) {
            return substr($digits, 0, 5) . '-*******-' . substr($digits, -1);
        }

        return $cnic;
    }

    private function statusLabel(?string $status): string
    {
        if (!$status) {
            return '—';
        }

        return ucwords(str_replace('_', ' ', $status));
    }
}

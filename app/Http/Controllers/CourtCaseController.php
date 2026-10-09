<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\CourtCase;
use App\Models\CourtReport;
use App\Services\SmsService;
use App\Services\SmsTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CourtCaseController extends Controller
{
    public function index()
    {
        $courtCases = CourtCase::visibleTo(request()->user())
            ->with('caseFile.enquiry', 'caseFile.investigationOfficer')
            ->latest()
            ->paginate(15);

        return response()->json($courtCases);
    }

    public function show(CourtCase $courtCase)
    {
        abort_unless(
            CourtCase::visibleTo(request()->user())->whereKey($courtCase->id)->exists(),
            404
        );

        $courtCase->load(
            'caseFile.enquiry.complaint',
            'caseFile.investigationOfficer',
            'hearings',
            'reports.submitter',
            'verdicts'
        );

        return response()->json($courtCase);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'case_id'     => 'required|integer|exists:cases,id',
            'court_name'  => 'required|string|max:255',
            'judge_name'  => 'nullable|string|max:255',
            'filing_date' => 'required|date',
        ]);

        $courtCase = DB::transaction(function () use ($data) {
            $caseFile = CaseFile::findOrFail($data['case_id']);
            $caseFile->update(['status' => 'in_progress']);

            return CourtCase::create([
                'case_id'     => $data['case_id'],
                'court_name'  => $data['court_name'],
                'judge_name'  => $data['judge_name'] ?? null,
                'filing_date' => $data['filing_date'],
                'status'      => 'filed',
            ]);
        });

        // Notify the complainant when a court case is filed.
        $this->notifyComplainantOfCourtCase($courtCase->fresh(), $data['filing_date']);

        return response()->json([
            'message' => 'Court case filed',
            'data'    => $courtCase->load('caseFile'),
        ], 201);
    }

    public function update(Request $request, CourtCase $courtCase)
    {
        $data = $request->validate([
            'court_name'  => 'sometimes|string|max:255',
            'judge_name'  => 'nullable|string|max:255',
            'status'      => 'nullable|string|max:50',
            'filing_date' => 'sometimes|date',
        ]);

        $courtCase->update($data);

        return response()->json([
            'message' => 'Court case updated',
            'data'    => $courtCase->fresh()->load('caseFile'),
        ]);
    }

    public function destroy(CourtCase $courtCase)
    {
        $courtCase->hearings()->delete();
        $courtCase->reports()->delete();
        $courtCase->verdicts()->delete();
        $courtCase->delete();

        return response()->json(['message' => 'Court case deleted']);
    }

    public function verdict(Request $request, CourtCase $courtCase)
    {
        $data = $request->validate([
            'verdict'      => 'required|string|in:conviction,acquittal,discharge,dismissed',
            'verdict_date' => 'required|date',
            'details'      => 'nullable|string|max:5000',
        ]);

        $verdict = DB::transaction(function () use ($data, $courtCase) {
            $verdict = $courtCase->verdicts()->create([
                'verdict'      => $data['verdict'],
                'verdict_date' => $data['verdict_date'],
                'details'      => $data['details'] ?? null,
            ]);

            $courtCase->update(['status' => 'verdict_given']);

            $caseFile = $courtCase->caseFile()->with('enquiry')->first();
            if ($caseFile) {
                $caseFile->update(['status' => 'closed']);

                $finalStatus = match ($data['verdict']) {
                    'conviction' => 'closed_conviction',
                    'acquittal'  => 'closed_acquittal',
                    'discharge'  => 'closed_discharge',
                    'dismissed'  => 'closed_dismissed',
                    default      => 'closed',
                };

                if ($caseFile->enquiry?->complaint_id) {
                    \App\Models\Complaint::whereKey($caseFile->enquiry->complaint_id)->update([
                        'status'       => 'closed',
                        'final_status' => $finalStatus,
                    ]);
                }
            }

            return $verdict;
        });

        // SMS the complainant about the verdict.
        $courtCase->load('caseFile.enquiry.complaint');
        $complaint = $courtCase->caseFile?->enquiry?->complaint;
        if ($complaint?->contact_no) {
            $digits = preg_replace('/\D+/', '', ($complaint->contact_country_code ?: '+92') . $complaint->contact_no);
            if ($digits) {
                app(SmsService::class)->sendBilingual(
                    $digits,
                    SmsTemplates::verdict($courtCase, str_replace('_', ' ', $data['verdict']), 'en'),
                    SmsTemplates::verdict($courtCase, str_replace('_', ' ', $data['verdict']), 'ur'),
                    [
                        'country_code'   => $complaint->contact_country_code ?: '+92',
                        'recipient_type' => 'complainant',
                        'subject_type'   => 'court_case',
                        'subject_id'     => $courtCase->id,
                        'trigger'        => 'verdict',
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Verdict recorded — case closed',
            'data'    => $verdict->load('courtCase.caseFile'),
        ], 201);
    }

    /**
     * SMS the complainant when their case is filed / goes to court.
     */
    protected function notifyComplainantOfCourtCase(CourtCase $courtCase, string $filingDate): void
    {
        $complaint = $courtCase->caseFile?->enquiry?->complaint;
        if (!$complaint?->contact_no) {
            return;
        }

        $digits = preg_replace('/\D+/', '', ($complaint->contact_country_code ?: '+92') . $complaint->contact_no);
        if (!$digits) {
            return;
        }

        app(SmsService::class)->sendBilingual(
            $digits,
            SmsTemplates::courtHearing($courtCase, \Carbon\Carbon::parse($filingDate), 'en'),
            SmsTemplates::courtHearing($courtCase, \Carbon\Carbon::parse($filingDate), 'ur'),
            [
                'country_code'   => $complaint->contact_country_code ?: '+92',
                'recipient_type' => 'complainant',
                'subject_type'   => 'court_case',
                'subject_id'     => $courtCase->id,
                'trigger'        => 'court_hearing',
            ]
        );
    }

    public function forwardReport(Request $request, CourtCase $courtCase)
    {
        $data = $request->validate([
            'report_type' => 'required|string|in:173_crpc,supplementary',
            'report_file' => 'required|file|max:20480|mimes:pdf,doc,docx',
        ]);

        $path = $request->file('report_file')->store('court-reports');

        $report = $courtCase->reports()->create([
            'report_type'  => $data['report_type'],
            'file_path'    => $path,
            'submitted_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Report forwarded to court',
            'data'    => $report->load('submitter'),
        ], 201);
    }
}

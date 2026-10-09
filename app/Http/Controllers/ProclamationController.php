<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnforcesWarrantApproval;
use App\Models\CaseFile;
use App\Models\Proclamation;
use App\Models\PropertyAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Proclamation (Section 87 CrPC) and Property Attachment (Section 88 CrPC)
 * on a registered case. Printing is gated by the admin's approval setting:
 * when "mandatory", a Circle Incharge-approved request is required first.
 */
class ProclamationController extends Controller
{
    use EnforcesWarrantApproval;

    private function assertCaseVisible(CaseFile $caseFile): void
    {
        abort_unless(
            CaseFile::visibleTo(request()->user())->whereKey($caseFile->id)->exists(),
            404
        );
    }

    public function index(Request $request, CaseFile $caseFile)
    {
        $this->assertCaseVisible($caseFile);

        return response()->json(
            Proclamation::where('case_file_id', $caseFile->id)
                ->with('attachments')
                ->latest()
                ->get()
        );
    }

    public function store(Request $request, CaseFile $caseFile)
    {
        $this->assertCaseVisible($caseFile);

        $data = $request->validate([
            'accused_name'        => 'required|string|max:255',
            'accused_father_name' => 'nullable|string|max:255',
            'accused_address'     => 'nullable|string|max:2000',
            'offence'             => 'nullable|string|max:2000',
            'court_name'          => 'nullable|string|max:255',
            'proclaimed_on'       => 'nullable|date',
            'appear_by_date'      => 'required|date',
            'publication_place'   => 'nullable|string|max:255',
        ]);

        // Section 87: the appearance date must be at least 30 days after publication.
        $proclaimedOn = isset($data['proclaimed_on']) ? Carbon::parse($data['proclaimed_on']) : now();
        $appearBy = Carbon::parse($data['appear_by_date']);
        if ($appearBy->lt($proclaimedOn->copy()->addDays(30))) {
            return response()->json([
                'message' => 'Under Section 87 CrPC, the appearance date must be at least 30 days after the proclamation date.',
                'errors'  => ['appear_by_date' => ['Must be at least 30 days after the proclamation date.']],
            ], 422);
        }

        $proclamation = Proclamation::create([
            'case_file_id'        => $caseFile->id,
            'fir_no'              => $caseFile->fir_no,
            'accused_name'        => $data['accused_name'],
            'accused_father_name' => $data['accused_father_name'] ?? null,
            'accused_address'     => $data['accused_address'] ?? null,
            'offence'             => $data['offence'] ?? null,
            'court_name'          => $data['court_name'] ?? null,
            'proclaimed_on'       => $proclaimedOn->toDateString(),
            'appear_by_date'      => $appearBy->toDateString(),
            'publication_place'   => $data['publication_place'] ?? null,
            'status'              => 'draft',
            'created_by'          => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Proclamation (u/s 87) recorded.',
            'data'    => $proclamation,
        ], 201);
    }

    public function printProclamation(Request $request, Proclamation $proclamation)
    {
        $caseFile = CaseFile::findOrFail($proclamation->case_file_id);
        $this->assertCaseVisible($caseFile);
        $this->assertWarrantApproved('proclamation', $caseFile->enquiry_id, $caseFile->id);

        $html = view('exports.proclamation', ['p' => $proclamation->load('caseFile')])->render();

        return response()->json(['html' => $html]);
    }

    public function storeAttachment(Request $request, Proclamation $proclamation)
    {
        $caseFile = CaseFile::findOrFail($proclamation->case_file_id);
        $this->assertCaseVisible($caseFile);

        $data = $request->validate([
            'property_type'        => 'required|string|in:movable,immovable,both',
            'property_description' => 'required|string|max:5000',
            'location'             => 'nullable|string|max:500',
            'estimated_value'      => 'nullable|numeric|min:0',
            'attachment_date'      => 'nullable|date',
            'order_no'             => 'nullable|string|max:100',
        ]);

        $attachment = PropertyAttachment::create(array_merge($data, [
            'proclamation_id' => $proclamation->id,
            'case_file_id'    => $caseFile->id,
            'status'          => 'draft',
            'created_by'      => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Property attachment (u/s 88) recorded.',
            'data'    => $attachment,
        ], 201);
    }

    public function printAttachment(Request $request, PropertyAttachment $attachment)
    {
        $caseFile = CaseFile::findOrFail($attachment->case_file_id);
        $this->assertCaseVisible($caseFile);
        $this->assertWarrantApproved('attachment', $caseFile->enquiry_id, $caseFile->id);

        $html = view('exports.attachment-order', [
            'a' => $attachment->load('proclamation', 'caseFile'),
        ])->render();

        return response()->json(['html' => $html]);
    }
}

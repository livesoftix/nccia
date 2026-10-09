<?php

namespace App\Http\Controllers;

use App\Models\ApprovalSetting;
use App\Models\CaseFile;
use App\Models\Enquiry;
use App\Models\WarrantRequest;
use Illuminate\Http\Request;

/**
 * Officer requests a warrant / proclamation / attachment; Circle Incharge
 * approves or rejects. Only relevant when the matching approval_setting is
 * "mandatory" — the print endpoints enforce that.
 */
class WarrantRequestController extends Controller
{
    public function index(Request $request)
    {
        $q = WarrantRequest::visibleTo($request->user())
            ->with(['requester:id,name', 'approver:id,name'])
            ->latest();

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($action = $request->query('action_key')) {
            $q->where('action_key', $action);
        }

        return response()->json($q->paginate(min(50, max(10, (int) $request->query('per_page', 15)))));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'action_key'   => 'required|string|in:' . implode(',', ApprovalSetting::ACTIONS),
            'enquiry_id'   => 'nullable|integer|exists:enquiries,id',
            'case_file_id' => 'nullable|integer|exists:cases,id',
            'remarks'      => 'nullable|string|max:2000',
            'details'      => 'nullable|array',
        ]);

        if (empty($data['enquiry_id']) && empty($data['case_file_id'])) {
            return response()->json(['message' => 'An enquiry or case must be linked to the request.'], 422);
        }

        // Derive the circle from the linked record so CI scoping works.
        $circleId = null;
        if (!empty($data['case_file_id'])) {
            $case = CaseFile::find($data['case_file_id']);
            $circleId = $case?->enquiry?->complaint?->circle_id ?? $case?->circle_id;
        } elseif (!empty($data['enquiry_id'])) {
            $enq = Enquiry::find($data['enquiry_id']);
            $circleId = $enq?->complaint?->circle_id ?? $enq?->circle_id;
        }
        $circleId = $circleId ?: $request->user()->circle_id;

        $wr = WarrantRequest::create([
            'action_key'   => $data['action_key'],
            'enquiry_id'   => $data['enquiry_id'] ?? null,
            'case_file_id' => $data['case_file_id'] ?? null,
            'circle_id'    => $circleId,
            'requested_by' => $request->user()->id,
            'status'       => 'pending',
            'remarks'      => $data['remarks'] ?? null,
            'details'      => $data['details'] ?? null,
        ]);

        return response()->json([
            'message' => 'Approval request submitted to Circle Incharge.',
            'data'    => $wr->load('requester:id,name'),
        ], 201);
    }

    public function approve(Request $request, WarrantRequest $warrantRequest)
    {
        abort_unless(
            WarrantRequest::visibleTo($request->user())->whereKey($warrantRequest->id)->exists(),
            403,
            'This request is outside your jurisdiction.'
        );

        $warrantRequest->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'decided_at'  => now(),
            'remarks'     => $request->input('remarks', $warrantRequest->remarks),
        ]);

        return response()->json([
            'message' => 'Request approved. The officer can now issue the document.',
            'data'    => $warrantRequest->fresh()->load('approver:id,name'),
        ]);
    }

    public function reject(Request $request, WarrantRequest $warrantRequest)
    {
        abort_unless(
            WarrantRequest::visibleTo($request->user())->whereKey($warrantRequest->id)->exists(),
            403,
            'This request is outside your jurisdiction.'
        );

        $data = $request->validate(['remarks' => 'required|string|max:2000']);

        $warrantRequest->update([
            'status'      => 'rejected',
            'approved_by' => $request->user()->id,
            'decided_at'  => now(),
            'remarks'     => $data['remarks'],
        ]);

        return response()->json([
            'message' => 'Request rejected.',
            'data'    => $warrantRequest->fresh()->load('approver:id,name'),
        ]);
    }
}

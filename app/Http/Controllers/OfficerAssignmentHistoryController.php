<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Enquiry;
use App\Models\Verification;
use App\Services\OfficerAssignmentService;
use Illuminate\Http\Request;

class OfficerAssignmentHistoryController extends Controller
{
    public function __construct(private OfficerAssignmentService $service)
    {
    }

    public function forVerification(Request $request, Verification $verification)
    {
        abort_unless(
            Verification::visibleTo($request->user())->whereKey($verification->id)->exists()
            || $request->user()->seesAllData()
            || $request->user()->hasAnyRole(['admin', 'circle_incharge']),
            403
        );

        return response()->json([
            'data' => $this->service->historyFor(OfficerAssignmentService::TYPE_VERIFICATION, $verification->id),
        ]);
    }

    public function forEnquiry(Request $request, Enquiry $enquiry)
    {
        abort_unless(
            Enquiry::visibleTo($request->user())->whereKey($enquiry->id)->exists()
            || $request->user()->seesAllData()
            || $request->user()->hasAnyRole(['admin', 'circle_incharge']),
            403
        );

        return response()->json([
            'data' => $this->service->historyFor(OfficerAssignmentService::TYPE_ENQUIRY, $enquiry->id),
        ]);
    }

    public function forCase(Request $request, CaseFile $caseFile)
    {
        abort_unless(
            CaseFile::visibleTo($request->user())->whereKey($caseFile->id)->exists()
            || $request->user()->seesAllData()
            || $request->user()->hasAnyRole(['admin', 'circle_incharge']),
            403
        );

        return response()->json([
            'data' => $this->service->historyFor(OfficerAssignmentService::TYPE_CASE, $caseFile->id),
        ]);
    }
}

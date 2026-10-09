<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Verification;
use App\Models\Enquiry;
use App\Models\CaseFile;
use App\Models\CourtCase;
use App\Models\CourtVerdict;

class MasterCaseController extends Controller
{
    public function timeline(Complaint $complaint)
    {
        $complaint->load('circle', 'operator');

        $verification = Verification::where('complaint_id', $complaint->id)
            ->with('officer', 'approvals')
            ->first();

        $enquiry = null;
        if ($verification) {
            $enquiry = Enquiry::where('complaint_id', $complaint->id)
                ->with('officer', 'activities.creator', 'approvals')
                ->first();
        }

        $caseFile = null;
        if ($enquiry) {
            $caseFile = CaseFile::where('enquiry_id', $enquiry->id)
                ->with('investigationOfficer', 'activities.creator', 'arrests', 'approvals')
                ->first();
        }

        $courtCase = null;
        $courtVerdict = null;
        if ($caseFile) {
            $courtCase = CourtCase::where('case_id', $caseFile->id)
                ->with('hearings', 'reports.submitter')
                ->first();

            if ($courtCase) {
                $courtVerdict = CourtVerdict::where('court_case_id', $courtCase->id)
                    ->latest()
                    ->first();
            }
        }

        return view('pages.case-timeline', compact(
            'complaint',
            'verification',
            'enquiry',
            'caseFile',
            'courtCase',
            'courtVerdict',
        ));
    }
}

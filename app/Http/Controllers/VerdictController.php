<?php

namespace App\Http\Controllers;

use App\Models\CourtCase;
use App\Models\CourtVerdict;
use App\Models\Complaint;
use App\Models\CaseFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerdictController extends Controller
{
    public function store(Request $request, CourtCase $courtCase)
    {
        $data = $request->validate([
            'verdict'      => 'required|string|in:conviction,acquittal',
            'verdict_date' => 'required|date',
            'details'      => 'nullable|string',
        ]);

        $result = DB::transaction(function () use ($data, $courtCase, $request) {
            $verdict = $courtCase->verdicts()->create($data);

            $courtCase->update(['status' => 'verdict_given']);

            $caseFile = $courtCase->caseFile;
            $caseFile->update(['status' => 'closed']);

            $finalStatus = $data['verdict'] === 'conviction' ? 'closed_conviction' : 'closed_acquittal';

            Complaint::where('id', $caseFile->enquiry->complaint_id)
                ->update(['final_status' => $finalStatus]);

            return $verdict;
        });

        return response()->json([
            'message' => 'Verdict recorded — case closed',
            'data'    => $result->load('courtCase.caseFile'),
        ], 201);
    }
}

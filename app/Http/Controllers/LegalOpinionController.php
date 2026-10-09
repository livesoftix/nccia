<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\EnquiryLegalOpinion;
use Illuminate\Http\Request;

class LegalOpinionController extends Controller
{
    public function store(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'role'        => 'required|string|max:30',
            'opinion_text' => 'required|string',
            'decision'    => 'nullable|string|max:20',
        ]);

        $opinion = $enquiry->legalOpinions()->create([
            'role'         => $data['role'],
            'opinion_text' => $data['opinion_text'],
            'decision'     => $data['decision'] ?? null,
            'created_by'   => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Legal opinion recorded',
            'data'    => $opinion->load('creator'),
        ], 201);
    }
}

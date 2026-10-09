<?php

namespace App\Http\Controllers;

use App\Models\CourtCase;
use App\Models\CourtHearing;
use Illuminate\Http\Request;

class HearingController extends Controller
{
    public function index(CourtCase $courtCase)
    {
        return response()->json(
            $courtCase->hearings()->latest('hearing_date')->get()
        );
    }

    public function store(Request $request, CourtCase $courtCase)
    {
        $data = $request->validate([
            'hearing_date'     => 'required|date',
            'type'             => 'required|string|in:trial_commencement,notice,statement,evidence,argument',
            'notes'            => 'nullable|string',
            'next_hearing_date' => 'nullable|date|after:hearing_date',
        ]);

        $hearing = $courtCase->hearings()->create($data);

        return response()->json([
            'message' => 'Hearing recorded',
            'data'    => $hearing,
        ], 201);
    }
}

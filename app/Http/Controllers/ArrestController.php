<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use Illuminate\Http\Request;

class ArrestController extends Controller
{
    public function index(CaseFile $caseFile)
    {
        return response()->json($caseFile->arrests()->latest()->get());
    }

    public function store(Request $request, CaseFile $caseFile)
    {
        $data = $request->validate([
            'accused_name'   => 'required|string|max:255',
            'cnic'           => 'required|string|max:15',
            'arrest_date'    => 'required|date',
            'remand_details' => 'nullable|string',
        ]);

        $arrest = $caseFile->arrests()->create($data);

        return response()->json([
            'message' => 'Arrest record added',
            'data'    => $arrest,
        ], 201);
    }

    public function update(Request $request, CaseFile $caseFile, Arrest $arrest)
    {
        $data = $request->validate([
            'accused_name'   => 'sometimes|string|max:255',
            'cnic'           => 'sometimes|string|max:15',
            'arrest_date'    => 'sometimes|date',
            'remand_details' => 'nullable|string',
        ]);

        $arrest->update($data);

        return response()->json([
            'message' => 'Arrest record updated',
            'data'    => $arrest->fresh(),
        ]);
    }

    public function destroy(CaseFile $caseFile, Arrest $arrest)
    {
        $arrest->delete();

        return response()->json(['message' => 'Arrest record removed']);
    }
}

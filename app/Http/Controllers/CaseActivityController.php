<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\CaseActivity;
use Illuminate\Http\Request;

class CaseActivityController extends Controller
{
    use \App\Http\Controllers\Concerns\EnforcesStageLock;

    public function index(CaseFile $caseFile)
    {
        return response()->json(
            $caseFile->activities()->with('creator')->latest()->get()
        );
    }

    public function store(Request $request, CaseFile $caseFile)
    {
        // Requirement #2 — a forwarded case is read-only for the line officer;
        // they cannot append further activities once it has moved on.
        if ($locked = $this->denyIfForwardLocked($caseFile, $request)) {
            return $locked;
        }

        $data = $request->validate([
            'type'          => 'required|string|max:30',
            'description'   => 'required|string',
            'activity_date' => 'required|date',
            'attachment'    => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('case-attachments');
        }

        $activity = $caseFile->activities()->create([
            'type'           => $data['type'],
            'description'    => $data['description'],
            'activity_date'  => $data['activity_date'],
            'attachment_path' => $path,
            'created_by'     => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Activity added',
            'data'    => $activity->load('creator'),
        ], 201);
    }
}

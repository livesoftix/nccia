<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EnquiryActivityController extends Controller
{
    use \App\Http\Controllers\Concerns\EnforcesStageLock;

    public function index(Enquiry $enquiry)
    {
        return response()->json($enquiry->activities()->with('creator')->latest()->get());
    }

    public function store(Request $request, Enquiry $enquiry)
    {
        // Requirement #2 — a forwarded record is read-only for the line officer;
        // they cannot append further activities once it has moved on.
        if ($locked = $this->denyIfForwardLocked($enquiry, $request)) {
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
            $path = $request->file('attachment')->store('enquiry-attachments');
        }

        $activity = $enquiry->activities()->create([
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

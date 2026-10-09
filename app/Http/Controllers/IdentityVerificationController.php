<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class IdentityVerificationController extends Controller
{
    public function index(Request $request)
    {
        $data = null;

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name'            => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\.\'-]+$/'],
                'email'           => ['required', 'email', 'max:255'],
                'phone'           => ['required', 'string', 'max:20', 'regex:/^[\d\+\-\(\)\s]+$/'],
                'verification_id' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\-]+$/'],
            ], [
                'name.regex'            => 'Name may only contain letters, spaces, dots, hyphens and apostrophes.',
                'phone.regex'           => 'Phone may only contain digits and basic formatting characters.',
                'verification_id.regex' => 'Verification ID may only contain letters, digits and hyphens.',
            ]);

            $data = $validated;
        }

        return view('pages.verify-details', compact('data'));
    }
}

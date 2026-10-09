<?php

namespace App\Http\Controllers;

use App\Models\ApprovalSetting;
use Illuminate\Http\Request;

/**
 * Admin-only screen to turn CI approval on (mandatory) or off (open) per action.
 */
class ApprovalSettingController extends Controller
{
    public function index()
    {
        return response()->json(
            ApprovalSetting::orderBy('id')->get(['id', 'action_key', 'label', 'requirement', 'updated_at'])
        );
    }

    public function update(Request $request, string $actionKey)
    {
        $data = $request->validate([
            'requirement' => 'required|string|in:mandatory,open',
        ]);

        $setting = ApprovalSetting::where('action_key', $actionKey)->firstOrFail();
        $setting->update([
            'requirement' => $data['requirement'],
            'updated_by'  => $request->user()->id,
        ]);
        ApprovalSetting::flushCache();

        return response()->json([
            'message' => "Approval for '{$setting->label}' set to " . strtoupper($data['requirement']),
            'setting' => $setting->fresh(),
        ]);
    }
}

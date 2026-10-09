<?php

namespace App\Http\Controllers;

use App\Models\SmsLog;
use App\Services\SmsService;
use App\Services\SmsTemplates;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function index(Request $request)
    {
        $query = SmsLog::query()->latest();

        if ($request->filled('trigger')) {
            $query->where('trigger', $request->trigger);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . preg_replace('/\D+/', '', $request->phone) . '%');
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $logs->through(fn (SmsLog $log) => [
                'id'            => $log->id,
                'phone'         => $log->display_phone,
                'message'       => $log->message,
                'lang'          => $log->lang,
                'trigger'       => $log->trigger,
                'recipient_type'=> $log->recipient_type,
                'subject_type'  => $log->subject_type,
                'subject_id'    => $log->subject_id,
                'status'        => $log->status,
                'response'      => $log->response,
                'created_at'    => $log->created_at?->toISOString(),
                'sent_at'       => $log->sent_at?->toISOString(),
            ]),
            'triggers' => [
                'complaint_registered',
                'complaint_status_update',
                'verification_assigned',
                'verification_submitted',
                'appearance_notice',
                'enquiry_assigned',
                'notice_issued',
                'non_appearance',
                'case_assigned',
                'court_hearing',
                'verdict',
                'forensic_handed_over',
                'manual',
            ],
            'statuses' => ['pending', 'sent', 'failed', 'disabled'],
        ]);
    }

    /**
     * Manually send an SMS to any number (admin / circle incharge).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'phone'   => ['required', 'string', 'regex:/^\+?[0-9\s-]{7,20}$/'],
            'message' => ['required', 'string', 'max:1600'],
            'lang'    => ['sometimes', 'string', 'in:en,ur,both'],
        ]);

        $digits = preg_replace('/\D+/', '', $validated['phone']);
        if (strlen($digits) === 10) {
            $digits = '92' . $digits;
        }

        $lang = $validated['lang'] ?? 'en';

        $options = [
            'trigger'        => 'manual',
            'recipient_type' => 'manual',
            'country_code'   => '+92',
        ];

        if ($lang === 'both') {
            app(SmsService::class)->sendBilingual($digits, $validated['message'], $validated['message'], $options);
        } else {
            $options['lang'] = $lang;
            app(SmsService::class)->send($digits, $validated['message'], $options);
        }

        return response()->json(['message' => 'SMS sent successfully']);
    }

    /**
     * Preview the bilingual templates for a given trigger (helps debugging).
     */
    public function templates(Request $request)
    {
        $trigger = $request->query('trigger');

        $sample = [
            'en' => '',
            'ur' => '',
        ];

        switch ($trigger) {
            case 'complaint_registered':
                $sample['en'] = SmsTemplates::complaintRegistered(new \App\Models\Complaint(['complainant_name' => 'John Doe']), 'en');
                $sample['ur'] = SmsTemplates::complaintRegistered(new \App\Models\Complaint(['complainant_name' => 'John Doe']), 'ur');
                break;
            default:
                $sample = null;
        }

        return response()->json(['trigger' => $trigger, 'templates' => $sample]);
    }
}
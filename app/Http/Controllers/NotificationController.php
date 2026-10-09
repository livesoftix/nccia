<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\EnquiryLegalOpinion;
use App\Models\Verification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'unread_count'  => 0,
                'notifications' => [],
            ]);
        }

        try {
            $notifications = $user->notifications()
                ->latest()
                ->limit(30)
                ->get()
                ->map(function ($n) {
                    return [
                        'id'         => $n->id,
                        'type'       => class_basename($n->type),
                        'data'       => $n->data,
                        'read_at'    => $n->read_at?->toISOString(),
                        'created_at' => $n->created_at?->toISOString(),
                    ];
                });

            $unreadCount = $user->unreadNotifications()->count();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch notifications: ' . $e->getMessage());
            $notifications = collect();
            $unreadCount = 0;
        }

        return response()->json([
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Pending tasks for the current user's role / stage (flowchart).
     */
    public function pendingTasks(Request $request)
    {
        $user = $request->user();
        $tasks = [];

        if (!$user) {
            return response()->json(['tasks' => [], 'count' => 0]);
        }

        // Personal assignments (any role)
        foreach (Verification::where('verification_officer_id', $user->id)
            ->whereIn('status', ['assigned', 'in_progress', 'sent_back'])
            ->with('complaint:id,tracking_no')
            ->orderByDesc('assigned_at')
            ->limit(15)
            ->get() as $v) {
            $tasks[] = [
                'module' => 'verification',
                'title'  => 'Verification — Complaint #' . ($v->complaint?->tracking_no ?? $v->complaint_id),
                'status' => $v->status,
                'url'    => '/verifications/' . $v->id . '/edit',
            ];
        }

        foreach (Enquiry::where('enquiry_officer_id', $user->id)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->with('complaint:id,tracking_no')
            ->orderByDesc('assignment_date')
            ->limit(15)
            ->get() as $e) {
            $tasks[] = [
                'module' => 'enquiry',
                'title'  => 'Enquiry ' . ($e->enquiry_number ?? ('#' . $e->id)),
                'status' => $e->status,
                'url'    => '/enquiries/' . $e->id . '/edit',
            ];
        }

        foreach (CaseFile::where('investigation_officer_id', $user->id)
            ->whereIn('status', ['assigned', 'in_progress', 'registered'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get() as $c) {
            $tasks[] = [
                'module' => 'case',
                'title'  => 'DAC Case' . ($c->fir_no ? ' (FIR #' . $c->fir_no . ')' : ' #' . $c->id),
                'status' => $c->status,
                'url'    => '/cases/' . $c->id . '/edit',
            ];
        }

        // Operator — only incomplete registrations (assign VO is on Complete Registration form)
        if ($user->hasRole('operator') && !$user->hasAnyRole(['admin', 'circle_incharge', 'director_general'])) {
            foreach (Complaint::query()
                ->whereIn('status', ['incomplete', 'registered'])
                ->where(function ($qq) use ($user) {
                    $qq->where('operator_id', $user->id)->orWhere('user_id', $user->id);
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get() as $c) {
                $tasks[] = [
                    'module' => 'complaint_registration',
                    'title'  => 'Complete Registration — #' . ($c->diary_no ?: $c->id),
                    'status' => $c->status,
                    'url'    => '/complaints/' . $c->id . '/edit',
                ];
            }
        }

        // Circle Incharge / Admin — approvals + unassigned
        if ($user->hasAnyRole(['circle_incharge', 'admin', 'director_general'])) {
            $circleOnly = $user->hasRole('circle_incharge')
                && $user->circle_id
                && !$user->hasAnyRole(['admin', 'director_general']);

            $vQuery = Verification::where('status', 'submitted')->with(['complaint:id,tracking_no,circle_id', 'officer:id,circle_id']);
            if ($circleOnly) {
                $vQuery->where(function ($q) use ($user) {
                    $q->whereHas('complaint', fn ($qq) => $qq->where('circle_id', $user->circle_id))
                      ->orWhereHas('officer', fn ($qq) => $qq->where('circle_id', $user->circle_id));
                });
            }
            foreach ($vQuery->orderByDesc('submitted_at')->limit(10)->get() as $v) {
                $tasks[] = [
                    'module' => 'verification_approval',
                    'title'  => 'Approve verification — #' . ($v->complaint?->tracking_no ?? $v->complaint_id),
                    'status' => $v->status,
                    'url'    => '/verifications',
                ];
            }

            $eQuery = Enquiry::where('status', 'cfr_submitted')->with('complaint:id,tracking_no,circle_id');
            if ($circleOnly) {
                $eQuery->whereHas('complaint', fn ($q) => $q->where('circle_id', $user->circle_id));
            }
            foreach ($eQuery->orderByDesc('submitted_at')->limit(10)->get() as $e) {
                $tasks[] = [
                    'module' => 'enquiry_approval',
                    'title'  => 'Approve CFR — Enquiry ' . ($e->enquiry_number ?? ('#' . $e->id)),
                    'status' => $e->status,
                    'url'    => '/enquiries',
                ];
            }

            $cQuery = Complaint::where('status', 'complete')
                ->whereNotNull('tracking_no')
                ->whereDoesntHave('verification');
            if ($circleOnly) {
                $cQuery->where('circle_id', $user->circle_id);
            }
            foreach ($cQuery->orderByDesc('id')->limit(8)->get() as $c) {
                $tasks[] = [
                    'module' => 'complaint_assign',
                    'title'  => 'Assign VO — Complaint #' . $c->tracking_no,
                    'status' => 'needs_assignment',
                    'url'    => '/complaints',
                ];
            }
        }

        // Reader Branch — missing enquiry numbers
        if ($user->hasAnyRole(['reader_branch', 'admin', 'director_general'])) {
            foreach (Enquiry::query()
                ->where(function ($q) {
                    $q->whereNull('enquiry_number')->orWhere('enquiry_number', '');
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get() as $e) {
                $tasks[] = [
                    'module' => 'enquiry_number',
                    'title'  => 'Generate Enquiry Number — Enquiry #' . $e->id,
                    'status' => 'registered',
                    'url'    => '/enquiries/' . $e->id . '/edit',
                ];
            }
        }

        // Moharrar — missing FIR numbers
        if ($user->hasAnyRole(['moharrar', 'admin', 'director_general'])) {
            foreach (CaseFile::query()
                ->where(function ($q) {
                    $q->whereNull('fir_no')->orWhere('fir_no', '');
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get() as $c) {
                $tasks[] = [
                    'module' => 'fir_number',
                    'title'  => 'Generate FIR Number — Case #' . $c->id,
                    'status' => $c->status,
                    'url'    => '/cases/' . $c->id . '/edit',
                ];
            }
        }

        // Legal — blank opinions
        if ($user->hasAnyRole(['ad_legal', 'dd_legal', 'additional_director', 'admin', 'director_general'])) {
            $roleKey = null;
            if ($user->hasRole('ad_legal')) {
                $roleKey = 'ad_legal';
            } elseif ($user->hasRole('dd_legal')) {
                $roleKey = 'dd_legal';
            } elseif ($user->hasRole('additional_director')) {
                $roleKey = 'additional_director';
            }

            $opinions = EnquiryLegalOpinion::query()
                ->where(function ($q) {
                    $q->whereNull('opinion_text')->orWhere('opinion_text', '');
                })
                ->when($roleKey, fn ($q) => $q->where('role', $roleKey))
                ->orderByDesc('id')
                ->limit(10)
                ->get();

            foreach ($opinions as $op) {
                $tasks[] = [
                    'module' => 'legal_opinion',
                    'title'  => 'Legal opinion pending — Enquiry #' . $op->enquiry_id,
                    'status' => 'pending',
                    'url'    => '/enquiries/' . $op->enquiry_id . '/edit',
                ];
            }
        }

        $seen = [];
        $tasks = array_values(array_filter($tasks, function ($t) use (&$seen) {
            $key = ($t['url'] ?? '') . '|' . ($t['title'] ?? '');
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));

        return response()->json([
            'tasks' => $tasks,
            'count' => count($tasks),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}

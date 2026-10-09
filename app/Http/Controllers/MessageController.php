<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Models\Enquiry;
use App\Models\CaseFile;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * List all active portal users the current user can message.
     */
    public function contacts(Request $request)
    {
        $users = User::whereHas('roles')
            ->where('id', '!=', $request->user()->id)
            ->with('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'designation'])
            ->map(function ($u) {
                return [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'designation' => $u->designation,
                    'role'        => $u->roles->first()?->name ?? 'user',
                ];
            });

        return response()->json($users);
    }

    /**
     * List direct conversations (distinct other users) with last message + unread count.
     */
    public function conversations(Request $request)
    {
        $me = $request->user()->id;

        $messages = Message::whereNull('enquiry_id')
            ->whereNull('case_file_id')
            ->where(function ($q) use ($me) {
                $q->where('sender_id', $me)->orWhere('receiver_id', $me);
            })
            ->orderByDesc('id')
            ->get();

        $otherIds = $messages
            ->map(fn ($m) => $m->sender_id === $me ? $m->receiver_id : $m->sender_id)
            ->filter()
            ->unique()
            ->values();

        $conversations = $otherIds->map(function ($otherId) use ($me) {
            $last = Message::whereNull('enquiry_id')
                ->whereNull('case_file_id')
                ->where(function ($q) use ($me, $otherId) {
                    $q->where('sender_id', $me)->where('receiver_id', $otherId);
                })->orWhere(function ($q) use ($me, $otherId) {
                    $q->where('sender_id', $otherId)->where('receiver_id', $me);
                })->orderByDesc('id')->first();

            $other = User::with('roles')->find($otherId);
            if (!$other) {
                return null;
            }

            return [
                'user' => [
                    'id'          => $other->id,
                    'name'        => $other->name,
                    'designation' => $other->designation,
                    'role'        => $other->roles->first()?->name ?? 'user',
                ],
                'last_message' => $last?->message,
                'last_time'    => $last?->created_at?->toISOString(),
                'unread'       => Message::whereNull('enquiry_id')
                    ->whereNull('case_file_id')
                    ->where('sender_id', $otherId)
                    ->where('receiver_id', $me)
                    ->where('is_read', false)
                    ->count(),
            ];
        })->filter()->sortByDesc('last_time')->values();

        return response()->json($conversations);
    }

    /**
     * Show direct thread between me and $user; marks incoming messages as read.
     */
    public function show(Request $request, User $user)
    {
        $me = $request->user();

        if ($user->id === $me->id) {
            return response()->json(['message' => 'You cannot open a conversation with yourself.'], 422);
        }

        Message::whereNull('enquiry_id')
            ->whereNull('case_file_id')
            ->where('sender_id', $user->id)
            ->where('receiver_id', $me->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        $messages = Message::between($me->id, $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('sender:id,name')
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'sender_id'  => $m->sender_id,
                'message'    => $m->message,
                'is_read'    => $m->is_read,
                'created_at' => $m->created_at?->toISOString(),
            ]);

        return response()->json($messages);
    }

    /**
     * Send direct message to another user.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'message'     => 'required|string|max:5000',
        ]);

        if ((int) $data['receiver_id'] === $request->user()->id) {
            return response()->json(['message' => 'You cannot send a message to yourself.'], 422);
        }

        $message = Message::create([
            'sender_id'   => $request->user()->id,
            'receiver_id' => $data['receiver_id'],
            'message'     => trim($data['message']),
        ]);

        return response()->json($message->load('sender:id,name'), 201);
    }

    /**
     * List all cases & enquiries available for case-specific collaboration chat.
     */
    public function caseConversations(Request $request)
    {
        $user = $request->user();

        // 1. Fetch accessible Enquiries
        $enquiries = Enquiry::visibleTo($user)
            ->with(['complaint:id,complainant_name,tracking_no,offence_type', 'officer:id,name,designation'])
            ->latest('id')
            ->take(80)
            ->get();

        // 2. Fetch accessible Cases
        $cases = CaseFile::visibleTo($user)
            ->with(['enquiry.complaint:id,complainant_name,tracking_no,offence_type', 'investigationOfficer:id,name,designation'])
            ->latest('id')
            ->take(80)
            ->get();

        $list = collect();

        foreach ($enquiries as $enq) {
            $caseNumber = $enq->enquiry_number ?: 'ENQ-' . str_pad($enq->id, 4, '0', STR_PAD_LEFT);
            $last = Message::where('enquiry_id', $enq->id)->orderByDesc('id')->first();
            $unreadCount = Message::where('enquiry_id', $enq->id)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->count();

            $officers = [];
            if ($enq->officer) {
                $officers[] = [
                    'id' => $enq->officer->id,
                    'name' => $enq->officer->name,
                    'designation' => $enq->officer->designation,
                    'role_label' => 'Enquiry Officer',
                ];
            }

            $list->push([
                'type'             => 'enquiry',
                'id'               => $enq->id,
                'case_number'      => $caseNumber,
                'reference_no'     => $enq->complaint?->tracking_no ?: ($enq->direct_info['reference_no'] ?? 'Direct'),
                'complainant_name' => $enq->complaint?->complainant_name ?: ($enq->direct_info['complainant_name'] ?? 'N/A'),
                'category'         => $enq->complaint?->offence_type ?: ($enq->direct_info['offence_type'] ?? 'General'),
                'status'           => $enq->status,
                'officers'         => $officers,
                'last_message'     => $last?->message,
                'last_time'        => $last?->created_at?->toISOString() ?: $enq->created_at?->toISOString(),
                'has_messages'     => (bool) $last,
                'unread_count'     => $unreadCount,
            ]);
        }

        foreach ($cases as $cf) {
            $caseNumber = $cf->fir_no ?: 'CASE-' . str_pad($cf->id, 4, '0', STR_PAD_LEFT);
            $last = Message::where('case_file_id', $cf->id)->orderByDesc('id')->first();
            $unreadCount = Message::where('case_file_id', $cf->id)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->count();

            $officers = [];
            if ($cf->investigationOfficer) {
                $officers[] = [
                    'id' => $cf->investigationOfficer->id,
                    'name' => $cf->investigationOfficer->name,
                    'designation' => $cf->investigationOfficer->designation,
                    'role_label' => 'Investigation Officer',
                ];
            }
            if ($cf->enquiry?->officer && $cf->enquiry->officer->id !== $cf->investigation_officer_id) {
                $officers[] = [
                    'id' => $cf->enquiry->officer->id,
                    'name' => $cf->enquiry->officer->name,
                    'designation' => $cf->enquiry->officer->designation,
                    'role_label' => 'Enquiry Officer',
                ];
            }

            $list->push([
                'type'             => 'case',
                'id'               => $cf->id,
                'case_number'      => $caseNumber,
                'reference_no'     => $cf->enquiry?->complaint?->tracking_no ?: ($cf->direct_info['reference_no'] ?? 'Direct'),
                'complainant_name' => $cf->enquiry?->complaint?->complainant_name ?: ($cf->direct_info['complainant_name'] ?? 'N/A'),
                'category'         => $cf->enquiry?->complaint?->offence_type ?: ($cf->direct_info['offence_type'] ?? 'DAC Case'),
                'status'           => $cf->status,
                'officers'         => $officers,
                'last_message'     => $last?->message,
                'last_time'        => $last?->created_at?->toISOString() ?: $cf->created_at?->toISOString(),
                'has_messages'     => (bool) $last,
                'unread_count'     => $unreadCount,
            ]);
        }

        // Sort: cases with unread first, then active messages, then latest time
        $sorted = $list->sortByDesc(function ($item) {
            $hasUnread = ($item['unread_count'] ?? 0) > 0 ? 2 : 0;
            $hasMsg = $item['has_messages'] ? 1 : 0;
            return ($hasUnread + $hasMsg) . '_' . $item['last_time'];
        })->values();

        return response()->json($sorted);
    }

    /**
     * Get thread for a specific case or enquiry room.
     */
    /**
     * May this user read/post in the thread for the given enquiry/case?
     * Mirrors the circle-scoped visibility used everywhere else.
     */
    private function userCanAccessThread(\App\Models\User $user, ?Enquiry $enquiry, ?CaseFile $caseFile): bool
    {
        if ($user->seesAllData()) {
            return true;
        }
        if ($enquiry) {
            return Enquiry::visibleTo($user)->whereKey($enquiry->id)->exists();
        }
        if ($caseFile) {
            return CaseFile::visibleTo($user)->whereKey($caseFile->id)->exists();
        }
        // Thread could not be tied to a case/enquiry: only all-data users (handled above) may see it.
        return false;
    }

    public function caseThread(Request $request, $caseId = null)
    {
        $type = $request->query('type') ?: $request->input('type'); // 'enquiry' or 'case'
        $id = $caseId ?: (int) $request->query('id');
        $caseNo = $request->query('case_number') ?: $request->input('case_number');
        $user = $request->user();

        // Resolve the thread's enquiry/case and enforce visibility before returning any messages.
        $resEnquiry = null; $resCase = null;
        if ($caseId && !is_numeric($caseId)) {
            $resCase = CaseFile::where('fir_no', $caseId)->first();
            if (!$resCase) { $resEnquiry = Enquiry::where('enquiry_number', $caseId)->first(); }
        } elseif ($type === 'enquiry' && $id) {
            $resEnquiry = Enquiry::find($id);
        } elseif ($type === 'case' && $id) {
            $resCase = CaseFile::find($id);
        } elseif ($id && is_numeric($id)) {
            $resCase = CaseFile::find($id);
            if (!$resCase) { $resEnquiry = Enquiry::find($id); }
        } elseif ($caseNo) {
            $resCase = CaseFile::where('fir_no', $caseNo)->first();
            if (!$resCase) { $resEnquiry = Enquiry::where('enquiry_number', $caseNo)->first(); }
        }
        if (!$this->userCanAccessThread($user, $resEnquiry, $resCase)) {
            return response()->json(['message' => 'Unauthorized. This case belongs to another jurisdiction.'], 403);
        }

        $query = Message::with(['sender:id,name,designation', 'sender.roles']);

        if ($caseId && !is_numeric($caseId)) {
            $query->where('case_number', $caseId);
        } elseif ($type === 'enquiry' && $id) {
            $query->where('enquiry_id', (int) $id);
        } elseif ($type === 'case' && $id) {
            $query->where('case_file_id', (int) $id);
        } elseif ($id && is_numeric($id)) {
            $query->where(function ($q) use ($id) {
                $q->where('case_file_id', (int) $id)
                  ->orWhere('enquiry_id', (int) $id);
            });
        } elseif ($caseNo) {
            $query->where('case_number', $caseNo);
        } else {
            return response()->json(['message' => 'Invalid case/enquiry identifier'], 422);
        }

        $messages = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($m) {
                return [
                    'id'                 => $m->id,
                    'sender_id'          => $m->sender_id,
                    'sender_name'        => $m->sender?->name ?? 'Unknown Officer',
                    'sender_designation' => $m->sender?->designation ?? '',
                    'sender_role'        => $m->sender?->roles->first()?->name ?? 'officer',
                    'message'            => $m->message,
                    'created_at'         => $m->created_at?->toISOString(),
                ];
            });

        return response()->json($messages);
    }

    /**
     * Send a message to a case / enquiry discussion room.
     */
    public function storeCaseMessage(Request $request, $caseId = null)
    {
        $type = $request->input('type', 'case');
        $id = $caseId ?: $request->input('id');

        $data = $request->validate([
            'type'        => 'nullable|in:enquiry,case',
            'id'          => 'nullable',
            'case_number' => 'nullable|string|max:100',
            'message'     => 'required|string|max:5000',
        ]);

        $sender = $request->user();
        $messageText = trim($data['message']);

        $caseNumber = $data['case_number'] ?? null;
        $enquiryId = null;
        $caseFileId = null;

        if ($type === 'enquiry') {
            $enquiry = is_numeric($id) ? Enquiry::find($id) : Enquiry::where('enquiry_number', $id)->first();
            if (!$enquiry) {
                return response()->json(['message' => 'Enquiry not found'], 404);
            }
            $enquiryId = $enquiry->id;
            $caseNumber = $caseNumber ?: ($enquiry->enquiry_number ?: 'ENQ-' . $enquiry->id);
        } else {
            $caseFile = is_numeric($id) ? CaseFile::find($id) : CaseFile::where('fir_no', $id)->first();
            if (!$caseFile) {
                // If not found as case, try finding as enquiry
                $enquiry = is_numeric($id) ? Enquiry::find($id) : Enquiry::where('enquiry_number', $id)->first();
                if ($enquiry) {
                    $enquiryId = $enquiry->id;
                    $caseNumber = $caseNumber ?: ($enquiry->enquiry_number ?: 'ENQ-' . $enquiry->id);
                } else {
                    return response()->json(['message' => 'Case/Enquiry not found'], 404);
                }
            } else {
                $caseFileId = $caseFile->id;
                $caseNumber = $caseNumber ?: ($caseFile->fir_no ?: 'CASE-' . $caseFile->id);
            }
        }

        // Enforce circle-scoped visibility before posting into a case/enquiry room.
        $guardEnquiry = $enquiryId ? Enquiry::find($enquiryId) : null;
        $guardCase = $caseFileId ? CaseFile::find($caseFileId) : null;
        if (!$this->userCanAccessThread($sender, $guardEnquiry, $guardCase)) {
            return response()->json(['message' => 'Unauthorized. This case belongs to another jurisdiction.'], 403);
        }

        $msg = Message::create([
            'sender_id'    => $sender->id,
            'receiver_id'  => null,
            'enquiry_id'   => $enquiryId,
            'case_file_id' => $caseFileId,
            'case_number'  => $caseNumber,
            'message'      => $messageText,
            'is_read'      => true,
            'read_at'      => now(),
        ]);

        $msg->load(['sender:id,name,designation', 'sender.roles']);

        return response()->json([
            'id'                 => $msg->id,
            'sender_id'          => $msg->sender_id,
            'sender_name'        => $msg->sender?->name ?? $sender->name,
            'sender_designation' => $msg->sender?->designation ?? $sender->designation,
            'sender_role'        => $msg->sender?->roles->first()?->name ?? $sender->roles->first()?->name ?? 'officer',
            'message'            => $msg->message,
            'created_at'         => $msg->created_at?->toISOString(),
        ], 201);
    }

    /**
     * Total unread message count for the current user (for the nav badge).
     */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => Message::where('receiver_id', $request->user()->id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    /**
     * Mark all incoming unread messages as read.
     */
    public function markAllRead(Request $request)
    {
        $count = Message::where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'All messages marked as read', 'marked' => $count]);
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Circle;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\Verification;
use App\Services\OfficerAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OfficerHandoverController extends Controller
{
    public function __construct(private OfficerAssignmentService $assignmentService)
    {
    }

    /**
     * Get active caseload and eligible replacement officers for a given officer.
     */
    public function caseload(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        // Security: only authorized supervisors (Admin, DG, Additional Director, or Circle Incharge of that circle)
        if (!$this->canManageOfficer($actor, $user)) {
            return response()->json(['message' => 'Unauthorized to manage this officer.'], 403);
        }

        $user->load(['circle:id,name,code', 'roles:id,name']);

        // 1. Active Verifications
        $verifications = Verification::query()
            ->where('verification_officer_id', $user->id)
            ->whereNotIn('status', ['submitted', 'approved', 'closed'])
            ->with('complaint:id,tracking_no,complainant_name,circle_id')
            ->latest()
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'tracking_no' => $v->complaint?->tracking_no ?: ('VER-' . $v->id),
                'complainant_name' => $v->complaint?->complainant_name ?: 'Citizen',
                'status' => $v->status ?: 'under_verification',
                'created_at' => $v->created_at ? $v->created_at->format('d M Y') : 'N/A',
            ]);

        // 2. Active Enquiries
        $enquiries = Enquiry::query()
            ->where('enquiry_officer_id', $user->id)
            ->whereNotIn('status', ['approved', 'closed'])
            ->with('complaint:id,tracking_no,complainant_name,circle_id')
            ->latest()
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'enquiry_number' => $e->enquiry_number ?: ('ENQ-' . $e->id),
                'tracking_no' => $e->complaint?->tracking_no ?: 'Direct Enquiry',
                'complainant_name' => $e->complaint?->complainant_name ?: 'Complainant',
                'status' => $e->status ?: 'assigned',
                'created_at' => $e->created_at ? $e->created_at->format('d M Y') : 'N/A',
            ]);

        // 3. Active Cases (FIRs)
        $cases = CaseFile::query()
            ->where('investigation_officer_id', $user->id)
            ->whereNotIn('status', ['closed'])
            ->with('complaint:id,tracking_no,complainant_name,circle_id')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'fir_no' => $c->fir_no ?: ('FIR-' . $c->id),
                'tracking_no' => $c->complaint?->tracking_no ?: 'Direct Case',
                'status' => $c->status ?: 'under_investigation',
                'created_at' => $c->created_at ? $c->created_at->format('d M Y') : 'N/A',
            ]);

        // 4. Eligible Replacement Officers
        // Candidates: Active officers in the same circle (or all circles for Admin/DG)
        $officersQuery = User::query()
            ->where('id', '!=', $user->id)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->with(['circle:id,name,code', 'roles:id,name']);

        if ($user->circle_id && !$actor->hasAnyRole(['admin', 'director_general'])) {
            $officersQuery->where('circle_id', $user->circle_id);
        }

        $getRoleCode = function ($role, $desig, $rolesCollection = null) {
            $candidates = [];
            if ($role) $candidates[] = strtolower(trim((string)$role));
            if ($desig) $candidates[] = strtolower(trim((string)$desig));
            if ($rolesCollection) {
                foreach ($rolesCollection as $r) {
                    if (!empty($r->name)) {
                        $candidates[] = strtolower(trim((string)$r->name));
                    }
                }
            }

            foreach ($candidates as $c) {
                if (str_contains($c, 'verif') || $c === 'vo') return 'vo';
            }
            foreach ($candidates as $c) {
                if (str_contains($c, 'enquir') || str_contains($c, 'inspector') || $c === 'eo' || $c === 'sho') return 'eo';
            }
            foreach ($candidates as $c) {
                if (str_contains($c, 'investig') || $c === 'io') return 'io';
            }
            foreach ($candidates as $c) {
                if (str_contains($c, 'incharge') || $c === 'ci') return 'ci';
            }
            return 'other';
        };

        $userRoleCode = $getRoleCode($user->role, $user->designation, $user->roles);

        $eligibleOfficers = $officersQuery->get()->map(function ($u) use ($userRoleCode, $getRoleCode) {
            $uRoleCode = $getRoleCode($u->role, $u->designation, $u->roles);
            $isSame = ($uRoleCode === $userRoleCode && $uRoleCode !== 'other');
            $primaryRole = $u->roles->first()?->name ?? $u->role ?? 'Officer';
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $primaryRole,
                'role_code' => $uRoleCode, // 'vo', 'eo', 'io', 'ci', 'other'
                'role_label' => ucwords(str_replace('_', ' ', $primaryRole)),
                'designation' => $u->designation ?: ucwords(str_replace('_', ' ', $primaryRole)),
                'circle_id' => $u->circle_id,
                'circle_name' => $u->circle?->name ?: 'National / HQ',
                'circle_code' => $u->circle?->code ?: '',
                'is_same_role' => $isSame,
            ];
        });

        // 5. Available Circles (for transfer)
        $circles = Circle::query()->select('id', 'name', 'code')->orderBy('name')->get();

        return response()->json([
            'officer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'role_code' => $userRoleCode,
                'designation' => $user->designation,
                'circle_id' => $user->circle_id,
                'circle_name' => $user->circle?->name ?: 'Islamabad HQ / National',
                'status' => $user->status ?: 'active',
                'status_remarks' => $user->status_remarks,
            ],
            'counts' => [
                'verifications' => $verifications->count(),
                'enquiries' => $enquiries->count(),
                'cases' => $cases->count(),
                'total_active' => $verifications->count() + $enquiries->count() + $cases->count(),
            ],
            'items' => [
                'verifications' => $verifications,
                'enquiries' => $enquiries,
                'cases' => $cases,
            ],
            'eligible_officers' => $eligibleOfficers,
            'circles' => $circles,
        ]);
    }

    /**
     * Execute officer lifecycle action (Transfer, Suspend, Workload Handover, or Reinstate).
     */
    public function executeHandover(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (!$this->canManageOfficer($actor, $user)) {
            return response()->json(['message' => 'Unauthorized to execute handover for this officer.'], 403);
        }

        $data = $request->validate([
            'action_type' => 'required|in:transfer,suspend,reassign,reinstate',
            'target_officer_id' => 'nullable|exists:users,id',
            'target_vo_id' => 'nullable|exists:users,id',
            'target_eo_id' => 'nullable|exists:users,id',
            'target_io_id' => 'nullable|exists:users,id',
            'new_circle_id' => 'nullable|exists:circles,id',
            'order_no' => 'nullable|string|max:120',
            'reason' => 'required|string|max:1000',
        ]);

        $actionType = $data['action_type'];
        $targetOfficerId = $data['target_officer_id'] ? (int) $data['target_officer_id'] : null;
        $targetVoId = !empty($data['target_vo_id']) ? (int) $data['target_vo_id'] : $targetOfficerId;
        $targetEoId = !empty($data['target_eo_id']) ? (int) $data['target_eo_id'] : $targetOfficerId;
        $targetIoId = !empty($data['target_io_id']) ? (int) $data['target_io_id'] : $targetOfficerId;

        $newCircleId = $data['new_circle_id'] ? (int) $data['new_circle_id'] : null;
        $orderNo = trim($data['order_no'] ?? '');
        $reason = trim($data['reason']);

        $fullReason = ($orderNo ? "[Order #{$orderNo}] " : "") . $reason;

        $targetOfficer = $targetOfficerId ? User::find($targetOfficerId) : null;

        $handoverCounts = [
            'verifications' => 0,
            'enquiries' => 0,
            'cases' => 0,
        ];

        DB::transaction(function () use (
            $user, $actionType, $targetOfficerId, $targetVoId, $targetEoId, $targetIoId,
            $newCircleId, $fullReason, $actor, &$handoverCounts
        ) {
            // 1. Reassign Verifications (to target_vo_id or target_officer_id)
            if ($targetVoId) {
                $verifications = Verification::query()
                    ->where('verification_officer_id', $user->id)
                    ->whereNotIn('status', ['submitted', 'approved', 'closed'])
                    ->get();

                foreach ($verifications as $ver) {
                    $this->assignmentService->reassign(
                        $ver,
                        OfficerAssignmentService::TYPE_VERIFICATION,
                        OfficerAssignmentService::ROLE_VO,
                        $targetVoId,
                        $actor->id,
                        $fullReason
                    );
                    $ver->update(['verification_officer_id' => $targetVoId]);
                    $handoverCounts['verifications']++;
                }
            }

            // 2. Reassign Enquiries (to target_eo_id or target_officer_id)
            if ($targetEoId) {
                $enquiries = Enquiry::query()
                    ->where('enquiry_officer_id', $user->id)
                    ->whereNotIn('status', ['approved', 'closed'])
                    ->get();

                foreach ($enquiries as $enq) {
                    $this->assignmentService->reassign(
                        $enq,
                        OfficerAssignmentService::TYPE_ENQUIRY,
                        OfficerAssignmentService::ROLE_EO,
                        $targetEoId,
                        $actor->id,
                        $fullReason
                    );
                    $enq->update(['enquiry_officer_id' => $targetEoId]);
                    $handoverCounts['enquiries']++;
                }
            }

            // 3. Reassign Cases (to target_io_id or target_officer_id)
            if ($targetIoId) {
                $cases = CaseFile::query()
                    ->where('investigation_officer_id', $user->id)
                    ->whereNotIn('status', ['closed'])
                    ->get();

                foreach ($cases as $case) {
                    $this->assignmentService->reassign(
                        $case,
                        OfficerAssignmentService::TYPE_CASE,
                        OfficerAssignmentService::ROLE_IO,
                        $targetIoId,
                        $actor->id,
                        $fullReason
                    );
                    $case->update(['investigation_officer_id' => $targetIoId]);
                    $handoverCounts['cases']++;
                }
            }

            // Update user status and circle
            if ($actionType === 'suspend') {
                $user->status = 'suspended';
                $user->status_remarks = $fullReason;
            } elseif ($actionType === 'transfer') {
                $user->status = 'active';
                if ($newCircleId) {
                    $user->circle_id = $newCircleId;
                }
                $user->status_remarks = $fullReason;
            } elseif ($actionType === 'reinstate') {
                $user->status = 'active';
                $user->status_remarks = "Reinstated to active duty. " . $fullReason;
            } elseif ($actionType === 'reassign') {
                $user->status = 'active';
                $user->status_remarks = "Workload handed over. " . $fullReason;
            }

            $user->save();
        });

        $totalHandedOver = $handoverCounts['verifications'] + $handoverCounts['enquiries'] + $handoverCounts['cases'];

        $actionLabel = match ($actionType) {
            'suspend' => 'suspended and active caseload handed over',
            'transfer' => 'transferred successfully and active caseload handed over',
            'reinstate' => 'reinstated to active duty',
            'reassign' => 'workload handed over successfully',
        };

        $msg = "Officer {$user->name} has been {$actionLabel}.";
        if ($totalHandedOver > 0 && $targetOfficer) {
            $msg .= " Total {$totalHandedOver} files safely transferred to {$targetOfficer->name} with full historical audit snapshots.";
        }

        return response()->json([
            'message' => $msg,
            'officer' => [
                'id' => $user->id,
                'name' => $user->name,
                'status' => $user->status,
                'status_remarks' => $user->status_remarks,
                'circle_id' => $user->circle_id,
            ],
            'handover_counts' => $handoverCounts,
            'total_handed_over' => $totalHandedOver,
        ]);
    }

    private function canManageOfficer(User $actor, User $target): bool
    {
        if ($actor->hasAnyRole(['admin', 'director_general', 'additional_director'])) {
            return true;
        }

        // Circle incharge can manage officers assigned to their same circle
        if ($actor->hasRole('circle_incharge') && $actor->circle_id && (int) $actor->circle_id === (int) $target->circle_id) {
            return true;
        }

        return false;
    }
}

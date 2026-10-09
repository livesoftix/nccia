<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Enquiry;
use App\Models\OfficerAssignmentHistory;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OfficerAssignmentService
{
    public const ROLE_VO = 'verification_officer';
    public const ROLE_EO = 'enquiry_officer';
    public const ROLE_IO = 'investigation_officer';

    public const TYPE_VERIFICATION = 'verification';
    public const TYPE_ENQUIRY = 'enquiry';
    public const TYPE_CASE = 'case';

    /**
     * Start or reassign an officer. Closes previous open tenure with a work snapshot.
     */
    public function reassign(
        Model $subject,
        string $subjectType,
        string $officerRole,
        ?int $newOfficerId,
        ?int $actorId = null,
        ?string $reason = null,
        bool $clearWorkFields = false,
    ): ?OfficerAssignmentHistory {
        $actorId = $actorId ?? Auth::id();
        $now = now();

        $previous = OfficerAssignmentHistory::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subject->id)
            ->whereNull('unassigned_at')
            ->latest('assigned_at')
            ->first();

        // Fallback: open row from current officer field if no history yet
        if (!$previous) {
            $currentOfficerId = $this->currentOfficerId($subject, $officerRole);
            if ($currentOfficerId && (int) $currentOfficerId !== (int) $newOfficerId) {
                $previous = OfficerAssignmentHistory::create([
                    'subject_type'  => $subjectType,
                    'subject_id'    => $subject->id,
                    'officer_role'  => $officerRole,
                    'officer_id'    => $currentOfficerId,
                    'assigned_by'   => $subject->assigned_by ?? null,
                    'assigned_at'   => $subject->assigned_at
                        ?? $subject->assignment_date
                        ?? $subject->created_at
                        ?? $now,
                ]);
            }
        }

        if ($previous && (int) $previous->officer_id !== (int) $newOfficerId) {
            $previous->update([
                'unassigned_at'  => $now,
                'unassigned_by'  => $actorId,
                'change_reason'  => $reason ?: 'Officer reassigned',
                'work_snapshot'  => $this->buildSnapshot($subject, $subjectType),
            ]);
        } elseif ($previous && (int) $previous->officer_id === (int) $newOfficerId) {
            return $previous;
        }

        if (!$newOfficerId) {
            return null;
        }

        return OfficerAssignmentHistory::create([
            'subject_type' => $subjectType,
            'subject_id'   => $subject->id,
            'officer_role' => $officerRole,
            'officer_id'   => $newOfficerId,
            'assigned_by'  => $actorId,
            'assigned_at'  => $now,
            'change_reason'=> $reason,
        ]);
    }

    /**
     * Record first assignment without closing anyone (create / initial assign).
     */
    public function recordInitial(
        Model $subject,
        string $subjectType,
        string $officerRole,
        int $officerId,
        ?int $actorId = null,
    ): OfficerAssignmentHistory {
        $exists = OfficerAssignmentHistory::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subject->id)
            ->whereNull('unassigned_at')
            ->where('officer_id', $officerId)
            ->exists();

        if ($exists) {
            return OfficerAssignmentHistory::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subject->id)
                ->whereNull('unassigned_at')
                ->where('officer_id', $officerId)
                ->latest('id')
                ->first();
        }

        return OfficerAssignmentHistory::create([
            'subject_type' => $subjectType,
            'subject_id'   => $subject->id,
            'officer_role' => $officerRole,
            'officer_id'   => $officerId,
            'assigned_by'  => $actorId ?? Auth::id(),
            'assigned_at'  => now(),
            'change_reason'=> 'Initial assignment',
        ]);
    }

    /**
     * Refresh work_snapshot on the active tenure (call on submit/save).
     */
    public function touchWorkSnapshot(Model $subject, string $subjectType): void
    {
        $open = OfficerAssignmentHistory::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subject->id)
            ->whereNull('unassigned_at')
            ->latest('assigned_at')
            ->first();

        if (!$open) {
            return;
        }

        $open->update([
            'work_snapshot' => $this->buildSnapshot($subject, $subjectType),
        ]);
    }

    public function historyFor(string $subjectType, int $subjectId)
    {
        return OfficerAssignmentHistory::query()
            ->with(['officer:id,name,designation', 'assignedByUser:id,name', 'unassignedByUser:id,name'])
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByDesc('assigned_at')
            ->get();
    }

    private function currentOfficerId(Model $subject, string $officerRole): ?int
    {
        return match ($officerRole) {
            self::ROLE_VO => $subject->verification_officer_id ?? null,
            self::ROLE_EO => $subject->enquiry_officer_id ?? null,
            self::ROLE_IO => $subject->investigation_officer_id ?? null,
            default => null,
        };
    }

    private function buildSnapshot(Model $subject, string $subjectType): array
    {
        $base = [
            'captured_at' => now()->toIso8601String(),
            'status'      => $subject->status ?? null,
        ];

        return match ($subjectType) {
            self::TYPE_VERIFICATION => array_merge($base, [
                'report_text'         => $subject->report_text,
                'recommendation'      => $subject->recommendation,
                'closure_reason'      => $subject->closure_reason,
                'priority_type'       => $subject->priority_type,
                'complainant_message' => $subject->complainant_message,
                'appeared_at'         => optional($subject->appeared_at)?->toIso8601String(),
                'submitted_at'        => optional($subject->submitted_at)?->toIso8601String(),
                'officer_name'        => $subject->officer?->name,
            ]),
            self::TYPE_ENQUIRY => array_merge($base, [
                'enquiry_number'  => $subject->enquiry_number,
                'recommendation'  => $subject->recommendation,
                'closure_reason'  => $subject->closure_reason,
                'cfr_summary'     => $subject->cfr_summary,
                'cfr_type'        => $subject->cfr_type ?? null,
                'technical_report'=> $subject->technical_report,
                'forensic_report' => $subject->forensic_report,
                'officer_name'    => $subject->officer?->name,
                'activities_count'=> method_exists($subject, 'activities') ? $subject->activities()->count() : null,
            ]),
            self::TYPE_CASE => array_merge($base, [
                'fir_no'           => $subject->fir_no,
                'recommendation'   => $subject->recommendation,
                'officer_name'     => $subject->investigationOfficer?->name,
                'activities_count' => method_exists($subject, 'activities') ? $subject->activities()->count() : null,
            ]),
            default => $base,
        };
    }
}

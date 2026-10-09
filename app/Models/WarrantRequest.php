<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantRequest extends Model
{
    protected $fillable = [
        'action_key', 'enquiry_id', 'case_file_id', 'circle_id',
        'requested_by', 'status', 'approved_by', 'decided_at', 'remarks', 'details',
    ];

    protected $casts = [
        'details'    => 'array',
        'decided_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function enquiry()
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_file_id');
    }

    /**
     * Has an APPROVED request already been granted for this action on this
     * enquiry/case? This is what the print endpoints check when the matching
     * approval_setting is mandatory.
     */
    public static function hasApproved(string $actionKey, ?int $enquiryId = null, ?int $caseFileId = null): bool
    {
        return static::query()
            ->where('action_key', $actionKey)
            ->where('status', 'approved')
            ->where(function ($q) use ($enquiryId, $caseFileId) {
                if ($enquiryId)  { $q->orWhere('enquiry_id', $enquiryId); }
                if ($caseFileId) { $q->orWhere('case_file_id', $caseFileId); }
            })
            ->exists();
    }

    /**
     * Circle-scoped visibility, mirroring the rest of the app.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->seesAllData()) {
            return $query;
        }
        return $query->where(function ($q) use ($user) {
            $q->where('circle_id', $user->circle_id)
              ->orWhere('requested_by', $user->id);
        });
    }
}

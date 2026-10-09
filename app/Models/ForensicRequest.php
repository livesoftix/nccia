<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForensicRequest extends Model
{
    protected $fillable = [
        'request_no', 'enquiry_id', 'case_id', 'submitted_by', 'destination', 'priority', 'note',
        'checklist_tech_report', 'checklist_seizure_memo', 'checklist_fir_copy', 'checklist_scope_letter',
        'checklist_audio_samples', 'checklist_summons', 'checklist_usb', 'checklist_transcript',
        'audio_script', 'audio_source_path', 'audio_sample_path', 'audio_sample_2_path', 'routed_to',
        'findings', 'lab_notes', 'status', 'is_external', 'external_ref', 'external_letter_no',
        'external_courier_no', 'external_organization', 'external_person_name', 'external_person_address',
        'external_person_contact', 'external_category', 'external_scope',
        'report_code', 'ad_reviewed_by', 'ad_reviewed_at', 'assigned_to',
        'assigned_at', 'opened_at', 'report_ready_at', 'desk_officer_id',
        'desk_notified_at', 'handed_over_at', 'handed_to_user_id', 'handover_remarks',
        'attachment_path', 'report_attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'is_external'             => 'boolean',
            'checklist_tech_report'   => 'boolean',
            'checklist_seizure_memo'  => 'boolean',
            'checklist_fir_copy'      => 'boolean',
            'checklist_scope_letter'  => 'boolean',
            'checklist_audio_samples' => 'boolean',
            'checklist_summons'       => 'boolean',
            'checklist_usb'           => 'boolean',
            'checklist_transcript'    => 'boolean',
            'ad_reviewed_at'          => 'datetime',
            'assigned_at'      => 'datetime',
            'opened_at'        => 'datetime',
            'report_ready_at'  => 'datetime',
            'desk_notified_at' => 'datetime',
            'handed_over_at'   => 'datetime',
        ];
    }

    protected $appends = [
        'attachment_url', 'audio_source_url', 'audio_sample_url', 'audio_sample_2_url', 'report_attachment_url',
    ];

    // Signed, authenticated URLs for sensitive forensic files (H1).
    public function getAttachmentUrlAttribute(): ?string
    {
        return \App\Services\SecureFileService::url($this->attachment_path, $this);
    }

    public function getAudioSourceUrlAttribute(): ?string
    {
        return \App\Services\SecureFileService::url($this->audio_source_path, $this);
    }

    public function getAudioSampleUrlAttribute(): ?string
    {
        return \App\Services\SecureFileService::url($this->audio_sample_path, $this);
    }

    public function getAudioSample2UrlAttribute(): ?string
    {
        return \App\Services\SecureFileService::url($this->audio_sample_2_path, $this);
    }

    public function getReportAttachmentUrlAttribute(): ?string
    {
        return \App\Services\SecureFileService::url($this->report_attachment_path, $this);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ForensicRequestItem::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function caseFile(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function adReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ad_reviewed_by');
    }

    public function deskOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desk_officer_id');
    }

    public function handedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_to_user_id');
    }
}

<?php

namespace App\Services;

use App\Models\{CaseActivity, CaseFile, Complaint, ComplaintPdfImport, CourtReport, Enquiry,
    EnquiryAccused, EnquiryActivity, EnquiryAttachment, EnquiryWitness, ForensicRequest, User, Verification, VerificationReport};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SecureFileAccessService
{
    private const RECORDS = [
        'user-signature' => [User::class, ['signature']],
        'complaint' => [Complaint::class, ['attachment', 'cnic_front', 'cnic_back', 'passport_attachment', 'picture', 'initial_accused']],
        'verification-report' => [VerificationReport::class, ['accused', 'evidence', 'transactions']],
        'verification' => [Verification::class, ['accused']],
        'enquiry' => [Enquiry::class, ['technical_report_attachment', 'forensic_report_attachment']],
        'enquiry-activity' => [EnquiryActivity::class, ['attachment_path']],
        'enquiry-attachment' => [EnquiryAttachment::class, ['file_path']],
        'enquiry-witness' => [EnquiryWitness::class, ['attachment', 'picture', 'statement_attachment']],
        'enquiry-accused' => [EnquiryAccused::class, ['cnic_attachment', 'passport_attachment', 'nadra_verisys_attachment']],
        'case-activity' => [CaseActivity::class, ['attachment_path']],
        'court-report' => [CourtReport::class, ['file_path']],
        'forensic-request' => [ForensicRequest::class, ['attachment_path', 'audio_source_path', 'audio_sample_path', 'audio_sample_2_path', 'report_attachment_path']],
        'pdf-import' => [ComplaintPdfImport::class, ['stored_path']],
    ];
    private const PATH_KEYS = ['file', 'file_path', 'attachment', 'attachment_path', 'cnic_front', 'cnic_back',
        'photo', 'picture', 'passport_attachment', 'other_attachment'];

    public static function typeFor(Model $record): ?string
    {
        foreach (self::RECORDS as $type => [$class]) {
            if ($record instanceof $class) {
                return $type;
            }
        }
        return null;
    }

    public static function find(string $type, mixed $id): ?Model
    {
        if (!isset(self::RECORDS[$type]) || !is_scalar($id) || !ctype_digit((string) $id)) {
            return null;
        }
        return self::RECORDS[$type][0]::find($id);
    }

    public static function containsPath(Model $record, string $path): bool
    {
        $type = self::typeFor($record);
        if (!$type) {
            return false;
        }
        foreach (self::RECORDS[$type][1] as $field) {
            if (in_array($path, self::paths($record->getAttribute($field), $field === 'attachment'), true)) {
                return true;
            }
        }
        return false;
    }

    private static function paths(mixed $value, bool $allowPathList = false): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? self::paths($decoded, $allowPathList) : [$value];
        }
        $paths = [];
        foreach (is_array($value) ? $value : [] as $key => $item) {
            if (is_array($item) || ($allowPathList && is_int($key)) || in_array($key, self::PATH_KEYS, true)) {
                $paths = array_merge($paths, self::paths($item));
            }
        }
        return $paths;
    }

    public static function canView(User $user, Model $record): bool
    {
        if ($user->isSuspended() || !$user->roles()->exists()) {
            return false;
        }
        if ($record instanceof User) {
            return (int) $record->id === (int) $user->id || $user->hasRole('admin');
        }
        if ($record instanceof Complaint) {
            return $user->can('view', $record);
        }
        if ($record instanceof Verification) {
            return $user->can('view', $record);
        }
        if ($record instanceof VerificationReport) {
            return VerificationReport::visibleTo($user)->whereKey($record->id)->exists();
        }
        if ($record instanceof Enquiry) {
            return $user->can('view', $record);
        }
        if ($record instanceof EnquiryActivity || $record instanceof EnquiryAttachment
            || $record instanceof EnquiryWitness || $record instanceof EnquiryAccused) {
            return $record->enquiry && self::canView($user, $record->enquiry);
        }
        if ($record instanceof CaseActivity) {
            return $record->caseFile && CaseFile::visibleTo($user)->whereKey($record->case_id)->exists();
        }
        if ($record instanceof CourtReport) {
            $case = $record->courtCase?->caseFile;
            return $case && CaseFile::visibleTo($user)->whereKey($case->id)->exists();
        }
        if ($record instanceof ComplaintPdfImport) {
            return (int) $record->user_id === (int) $user->id || $user->hasRole('admin');
        }
        if ($record instanceof ForensicRequest) {
            if ($user->hasRole('admin')) {
                return true;
            }
            if ($user->hasAnyRole(['admin_forensic', 'dd_forensic', 'ad_forensic'])) {
                return $record->destination === 'forensic';
            }
            if ($user->hasRole('forensic_team')) {
                return (int) $record->assigned_to === (int) $user->id;
            }
            if ($user->hasRole('desk_forensic')) {
                return in_array($record->status, ['report_ready', 'handed_over'], true);
            }
            if ($record->enquiry_id) {
                return Enquiry::visibleTo($user)->whereKey($record->enquiry_id)->exists();
            }
            if ($record->case_id) {
                return CaseFile::visibleTo($user)->whereKey($record->case_id)->exists();
            }
            return (int) $record->submitted_by === (int) $user->id
                || (int) $record->handed_to_user_id === (int) $user->id;
        }
        return false;
    }

    /** Submitted paths may only refer to an existing attachment the caller can read. */
    public static function validateRetainedPaths(User $user, mixed $values, string $field = 'attachments'): void
    {
        if (is_string($values)) {
            $values = json_decode($values, true);
        }
        foreach (is_array($values) ? $values : [] as $key => $value) {
            if (is_array($value)) {
                self::validateRetainedPaths($user, $value, $field);
            } elseif (is_string($value) && $value !== '' && in_array($key, array_merge(self::PATH_KEYS,
                ['cnic_attachment', 'nadra_verisys_attachment', 'statement_attachment']), true)) {
                if (!self::mayReusePath($user, $value)) {
                    throw ValidationException::withMessages([$field => 'A retained file must belong to an accessible record. Upload a new file instead.']);
                }
            }
        }
    }

    private static function mayReusePath(User $user, string $path): bool
    {
        if (!SecureFileService::normalize($path)) {
            return false;
        }
        $cacheKey = 'secure-file-reuse:' . $user->id . ':' . hash('sha256', $path);
        if (request()->attributes->get($cacheKey) === true) {
            return true;
        }
        foreach (self::RECORDS as [$class, $fields]) {
            $model = new $class;
            if (!Schema::hasTable($model->getTable())) {
                continue;
            }
            $fields = array_intersect($fields, Schema::getColumnListing($model->getTable()));
            if (!$fields) {
                continue;
            }
            $query = $class::query()->where(function ($query) use ($fields, $path) {
                foreach ($fields as $field) {
                    $query->orWhere($field, $path)->orWhere($field, 'like', '%' . $path . '%');
                }
            });
            foreach ($query->cursor() as $record) {
                if (self::containsPath($record, $path) && self::canView($user, $record)) {
                    request()->attributes->set($cacheKey, true);
                    return true;
                }
            }
        }
        return false;
    }
}

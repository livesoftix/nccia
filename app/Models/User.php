<?php

namespace App\Models;

use App\Notifications\GeneralNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles {
        hasRole as traitHasRole;
        hasAnyRole as traitHasAnyRole;
    }

    protected $fillable = ['name', 'email', 'password', 'role', 'designation', 'circle_id', 'zone_id', 'signature', 'phone', 'country_code', 'status', 'status_remarks'];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'mfa_last_counter',
        'security_version',
    ];

    public function isSuspended(): bool
    {
        return strtolower((string) ($this->status ?? 'active')) === 'suspended';
    }

    protected static function booted(): void
    {
        static::updating(function (User $user) {
            if ($user->isDirty(['password', 'email', 'status', 'role', 'circle_id', 'zone_id', 'mfa_secret', 'mfa_confirmed_at'])) {
                $user->security_version = (int) ($user->getOriginal('security_version') ?? 1) + 1;
                $user->remember_token = null;
                if (\Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
                    $user->tokens()->delete();
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('password_reset_tokens')) {
                    \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                        ->whereIn('email', array_filter([$user->getOriginal('email'), $user->email]))->delete();
                }
                \Illuminate\Support\Facades\Cache::forget('user_online_' . $user->id);
            }
        });
        static::deleting(function (User $user) {
            if (\Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
                $user->tokens()->delete();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('password_reset_tokens')) {
                \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }
            \Illuminate\Support\Facades\Cache::forget('user_online_' . $user->id);
        });
    }

    /** Use after assigned permission/role pivots change without a user column update. */
    public function invalidateAuthentication(): void
    {
        $this->forceFill(['security_version' => (int) $this->security_version + 1, 'remember_token' => null])->save();
        if (\Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
            $this->tokens()->delete();
        }
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $this->email)->delete();
        \Illuminate\Support\Facades\Cache::forget('user_online_' . $this->id);
    }

    /**
     * Field / "line" officers whose work-product is frozen the moment they
     * forward a record to the next stage (requirement #2 — role-based locking).
     * These roles create entries; once forwarded, they keep read access only.
     */
    public const LINE_OFFICER_ROLES = [
        'operator', 'front_desk_officer',
        'verification_officer',
        'enquiry_officer',
        'investigation_officer',
        'inspector', 'sub_inspector', 'officer',
    ];

    /**
     * True when the user acts as a line officer and NOT as a supervisor.
     * A supervisor hat (admin / DG) takes precedence so dual-role accounts
     * are never locked out of their correction duties.
     */
    public function isLineOfficer(): bool
    {
        if ($this->canOverrideStageLock()) {
            return false;
        }

        return $this->hasAnyRole(self::LINE_OFFICER_ROLES);
    }

    /**
     * Who may still correct a forwarded record (the audited "break-glass").
     * Every such edit is written to the immutable audit ledger by AuditObserver,
     * so corrections are permitted but never silent.
     */
    public function canOverrideStageLock(): bool
    {
        return $this->hasAnyRole(['admin', 'director_general']);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_confirmed_at' => 'datetime',
            'mfa_last_counter' => 'integer',
            'security_version' => 'integer',
        ];
    }

    public function hasRole($roles, string $guard = null): bool
    {
        $userRole = strtolower(trim((string) ($this->role ?? '')));

        // Roles are granted ONLY by the validated `role` column (set from the
        // roles table) and by assigned Spatie roles — never inferred from the
        // free-text `designation`, which a circle incharge can set and which
        // previously allowed silent privilege escalation.
        $checkSingle = function ($role) use ($userRole) {
            $r = strtolower(trim((string) $role));
            if ($userRole === $r) return true;
            // Safe exact-equivalence aliases only (canonical role names, no substring/designation inference).
            if ($r === 'operator' && $userRole === 'front_desk_officer') return true;
            if ($r === 'front_desk_officer' && $userRole === 'operator') return true;
            if ($r === 'admin' && $userRole === 'superadmin') return true;
            return false;
        };

        if (is_string($roles)) {
            if ($checkSingle($roles)) return true;
        } elseif (is_array($roles)) {
            foreach ($roles as $r) {
                if ($checkSingle($r)) return true;
            }
        }

        try {
            return $this->traitHasRole($roles, $guard);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasAnyRole(...$roles): bool
    {
        $flattened = is_array($roles[0] ?? null) ? $roles[0] : $roles;
        foreach ($flattened as $r) {
            if ($this->hasRole($r)) return true;
        }
        return false;
    }

    /** Notify admins + director general of a security event. Never throws. */
    public static function notifySupervisors(string $type, string $message, array $extra = []): void
    {
        try {
            static::role(['admin', 'director_general'])
                ->where('status', 'active')
                ->get()
                ->each(fn ($u) => $u->notify(new GeneralNotification($type, $message, null, $extra)));
        } catch (\Throwable) {
            // A failing notification must never break the request it is reporting on.
        }
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }

    public function findForPassport($username)
    {
        return $this->where('email', $username)->first();
    }

    protected $appends = [
        'mfa_required',
        'mfa_enabled',
        'mfa_enrollment_required',
        'circle_code',
        'circle_name',
        'zone_code',
        'zone_name',
        'is_zonal_head',
    ];

    public function requiresMfa(): bool
    {
        return config('security.mfa.require_all', false)
            || $this->hasAnyRole(config('security.mfa.required_roles', []));
    }

    public function getMfaEnabledAttribute(): bool
    {
        return $this->mfa_confirmed_at !== null;
    }

    public function getMfaRequiredAttribute(): bool
    {
        return $this->requiresMfa();
    }

    public function getMfaEnrollmentRequiredAttribute(): bool
    {
        return $this->requiresMfa() && !$this->mfa_enabled;
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function circle()
    {
        return $this->belongsTo(Circle::class);
    }

    public function getCircleIdAttribute($value): ?int
    {
        if ($value) {
            return (int) $value;
        }

        // Auto-detect circle from name, email, or designation for unassigned legacy accounts
        $haystack = strtolower(($this->attributes['email'] ?? '') . ' ' . ($this->attributes['name'] ?? '') . ' ' . ($this->attributes['designation'] ?? ''));
        
        $code = null;
        if (str_contains($haystack, 'lhr') || str_contains($haystack, 'lahore')) {
            $code = 'LHR';
        } elseif (str_contains($haystack, 'grw') || str_contains($haystack, 'gujranwala')) {
            $code = 'GRW';
        } elseif (str_contains($haystack, 'rwp') || str_contains($haystack, 'rawalpindi')) {
            $code = 'RWP';
        } elseif (str_contains($haystack, 'mux') || str_contains($haystack, 'multan')) {
            $code = 'MUX';
        } elseif (str_contains($haystack, 'fsd') || str_contains($haystack, 'faisalabad')) {
            $code = 'FSD';
        } elseif (str_contains($haystack, 'pew') || str_contains($haystack, 'peshawar')) {
            $code = 'PEW';
        } elseif (str_contains($haystack, 'khi') || str_contains($haystack, 'karachi')) {
            $code = 'KHI';
        } elseif (str_contains($haystack, 'uet') || str_contains($haystack, 'quetta')) {
            $code = 'UET';
        } elseif (str_contains($haystack, 'gwd') || str_contains($haystack, 'gwadar')) {
            $code = 'GWD';
        } elseif (str_contains($haystack, 'glt') || str_contains($haystack, 'gilgit')) {
            $code = 'GLT';
        } elseif (str_contains($haystack, 'atd') || str_contains($haystack, 'abbottabad')) {
            $code = 'ATD';
        } elseif (str_contains($haystack, 'dik') || str_contains($haystack, 'ismail')) {
            $code = 'DIK';
        } elseif (str_contains($haystack, 'skr') || str_contains($haystack, 'sukkur')) {
            $code = 'SKR';
        }

        if ($code) {
            $circle = Circle::where('code', $code)->first();
            if ($circle) {
                return (int) $circle->id;
            }
        }

        // Default Circle Incharge with null circle to Lahore Central Directorate
        if (($this->attributes['role'] ?? '') === 'circle_incharge') {
            $circle = Circle::where('code', 'LHR')->first() ?? Circle::first();
            if ($circle) {
                return (int) $circle->id;
            }
        }

        return null;
    }

    public function getCircleCodeAttribute(): ?string
    {
        return $this->circle?->code;
    }

    public function getCircleNameAttribute(): ?string
    {
        return $this->circle?->name;
    }

    public function getZoneCodeAttribute(): ?string
    {
        return $this->zone?->code ?: $this->circle?->zone?->code;
    }

    public function getZoneNameAttribute(): ?string
    {
        return $this->zone?->name ?: $this->circle?->zone?->name;
    }

    public function getIsZonalHeadAttribute(): bool
    {
        return $this->isZonalHead();
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature ? \App\Services\SecureFileService::url($this->signature, $this) : null;
    }

    /**
     * Check if user belongs to Islamabad / National Headquarters.
     */
    public function isHeadquarters(): bool
    {
        // Station/Circle roles are strictly local station officers, NEVER Headquarters!
        $role = strtolower(trim((string) ($this->role ?? '')));
        if (in_array($role, ['circle_incharge', 'operator', 'front_desk_officer', 'verification_officer', 'enquiry_officer', 'investigation_officer', 'moharrar', 'reader_branch'], true)) {
            return false;
        }

        // 1. If user is explicitly assigned to a regional circle (e.g. Lahore, Gujranwala, Karachi, etc.)
        // then they are strictly a regional officer/executive, NEVER Headquarters!
        if ($this->circle_id) {
            $circle = $this->circle;
            if ($circle) {
                $code = strtoupper(trim((string)$circle->code));
                $name = strtolower(trim((string)$circle->name));
                return ($code === 'HQ' || $code === 'ISL' || $code === 'ISB' || str_contains($name, 'islamabad') || str_contains($name, 'headquarter'));
            }
            return false;
        }

        // 2. Unassigned circle_id: Admin, DG, and Federal Directorate officers operate at Headquarters
        return $this->hasAnyRole(['admin', 'director_general', 'additional_director', 'ad_legal', 'dd_legal'])
            || in_array(strtolower($this->role ?? ''), ['admin', 'director_general', 'additional_director', 'ad_legal', 'dd_legal'], true);
    }

    /**
     * Check if user is a Zonal / Regional Head (e.g. Director Punjab / Lahore Central Directorate overseeing Punjab Zone).
     */
    public function isZonalHead(): bool
    {
        if ($this->seesAllData()) {
            return false;
        }
        $effectiveZoneId = $this->zone_id ?: $this->circle?->zone_id;
        if (!$effectiveZoneId) {
            return false;
        }

        $userRole = strtolower(trim((string) ($this->role ?? '')));

        // A Circle Incharge is strictly circle-level, not zonal head
        if ($userRole === 'circle_incharge' || str_contains($userRole, 'circle_incharge')) {
            return false;
        }

        return $this->hasAnyRole(['zonal_director', 'director', 'additional_director', 'director_punjab']);
    }

    /**
     * Check whether this user has permission to access records/actions belonging to a specific circle.
     */
    public function canAccessCircle(?int $targetCircleId): bool
    {
        if (!$targetCircleId) {
            return false;
        }

        if ($this->seesAllData()) {
            return true;
        }

        if ($this->isZonalHead()) {
            $effectiveZoneId = $this->zone_id ?: $this->circle?->zone_id;
            if ($effectiveZoneId) {
                $targetCircle = Circle::find($targetCircleId);
                return $targetCircle && (int) $targetCircle->zone_id === (int) $effectiveZoneId;
            }
        }

        return (int) $this->circle_id === (int) $targetCircleId;
    }

    /**
     * Who sees all records across Pakistan:
     * Only Admin, Director General, or officers stationed at Islamabad / Headquarters.
     * Regional circle officers (Gujranwala, Lahore, Karachi, etc.) NEVER see all data.
     */
    public function seesAllData(): bool
    {
        // If assigned to a regional circle (e.g. Lahore, Gujranwala, Karachi, etc.), they do NOT see nationwide data!
        if ($this->circle_id && !$this->isHeadquarters()) {
            return false;
        }

        return $this->isHeadquarters();
    }

    /**
     * Forensic portal roles (isolated from the main NCCIA modules).
     */
    public const FORENSIC_ROLES = [
        'admin_forensic',
        'dd_forensic',
        'ad_forensic',
    ];

    public function isForensic(): bool
    {
        return $this->hasAnyRole(self::FORENSIC_ROLES);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\InvestigationOfficer;
use App\Models\Otp;
use App\Models\User;
use App\Models\Zone;
use App\Services\OtpMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Rules\StrongPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class InvestigationOfficerController extends Controller
{
    private function generateStrongPassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function scopedQuery(?User $user = null)
    {
        $user = $user ?? request()->user();
        $query = InvestigationOfficer::query()->with('user:id,name,email');

        if ($user && $user->hasRole('circle_incharge') && $user->circle_id
            && !$user->hasAnyRole(['admin', 'director_general'])) {
            $circle = $user->circle;
            if ($circle) {
                $query->where(function ($q) use ($circle) {
                    $q->where('circle', $circle->name)
                      ->orWhere('circle', $circle->code);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    private function resolveCircleZoneIds(InvestigationOfficer $officer): array
    {
        $data = [];

        if ($officer->circle) {
            $circle = Circle::where(function ($q) use ($officer) {
                $q->where('name', $officer->circle)->orWhere('code', $officer->circle);
            })->first();
            if ($circle) {
                $data['circle_id'] = $circle->id;
            }
        }

        if ($officer->zone) {
            $zone = Zone::where(function ($q) use ($officer) {
                $q->where('name', $officer->zone)->orWhere('code', $officer->zone);
            })->first();
            if ($zone) {
                $data['zone_id'] = $zone->id;
            }
        }

        return $data;
    }

    private function createOrUpdatePortalUser(InvestigationOfficer $officer, string $email, string $password): User
    {
        return DB::transaction(function () use ($officer, $email, $password) {
        $officer = $this->lockedOfficer(request(), $officer);
        abort_if($officer->user_id, 422, 'Portal access already granted.');
        $email = strtolower($email);
        abort_if(User::where('email', $email)->lockForUpdate()->exists(), 422,
            'This email is already assigned to an account. Use an unassigned email.');
        $userData = array_merge([
            'name'        => $officer->name,
            'password'    => Hash::make($password),
            'designation' => $officer->designation,
            'role'        => 'investigation_officer',
        ], $this->resolveCircleZoneIds($officer));
        $user = User::create($userData + ['email' => $email]);

        if (!$user->hasRole('investigation_officer')) {
            $user->assignRole('investigation_officer');
        }

        $officer->update(['user_id' => $user->id, 'email' => $email]);

        return $user->fresh();
        }, 3);
    }

    public function index()
    {
        $this->authorize('viewAny', InvestigationOfficer::class);

        $perPage = min(50, max(10, (int) request('per_page', 15)));
        $search = trim((string) request('search', ''));

        $query = $this->scopedQuery()->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search . '%')
                  ->orWhere('badge_no', 'like', $search . '%')
                  ->orWhere('email', 'like', $search . '%')
                  ->orWhere('designation', 'like', $search . '%');
            });
        }

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        $officers = $query->paginate($perPage)->withQueryString();

        $base = $this->scopedQuery();
        $stats = [
            'total'       => (clone $base)->count(),
            'active'      => (clone $base)->where('status', 'active')->count(),
            'with_access' => (clone $base)->whereNotNull('user_id')->count(),
        ];

        if (request()->expectsJson()) {
            return response()->json(array_merge($officers->toArray(), ['stats' => $stats]));
        }

        return view('investigation-officers.index', compact('officers', 'stats'));
    }

    public function show(InvestigationOfficer $investigationOfficer)
    {
        $this->authorize('view', $investigationOfficer);
        $investigationOfficer->load('user:id,name,email,designation,circle_id,zone_id');

        if (request()->expectsJson()) {
            return response()->json(['data' => $investigationOfficer]);
        }

        return view('investigation-officers.show', compact('investigationOfficer'));
    }

    public function create()
    {
        $this->authorize('create', InvestigationOfficer::class);

        return view('investigation-officers.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', InvestigationOfficer::class);

        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'badge_no'             => 'required|string|max:50|unique:investigation_officers,badge_no',
            'designation'          => 'nullable|string|max:255',
            'circle'               => 'nullable|string|max:255',
            'zone'                 => 'nullable|string|max:255',
            'contact_no'           => 'nullable|string|max:20',
            'contact_country_code' => 'nullable|string|max:8',
            'email'                => 'nullable|email|max:255',
            'address'              => 'nullable|string|max:1000',
            'date_of_joining'      => 'nullable|date',
            'status'               => 'nullable|in:active,inactive',
            'remarks'              => 'nullable|string|max:2000',
        ]);

        $user = $request->user();
        if ($user->hasRole('circle_incharge') && $user->circle
            && !$user->hasAnyRole(['admin', 'director_general'])) {
            $data['circle'] = $user->circle->name;
            if ($user->zone) {
                $data['zone'] = $user->zone->name;
            }
        }

        $data['status'] = $data['status'] ?? 'active';
        $data['contact_country_code'] = $data['contact_country_code'] ?? '+92';

        $officer = InvestigationOfficer::create($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Investigation Officer added successfully',
                'data'    => $officer,
            ], 201);
        }

        return redirect()->route('investigation-officers.index')
            ->with('success', 'Investigation Officer added successfully');
    }

    public function edit(InvestigationOfficer $investigationOfficer)
    {
        $this->authorize('update', $investigationOfficer);

        return view('investigation-officers.edit', compact('investigationOfficer'));
    }

    public function update(Request $request, InvestigationOfficer $investigationOfficer)
    {
        $this->authorize('update', $investigationOfficer);

        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'badge_no'             => 'required|string|max:50|unique:investigation_officers,badge_no,' . $investigationOfficer->id,
            'designation'          => 'nullable|string|max:255',
            'circle'               => 'nullable|string|max:255',
            'zone'                 => 'nullable|string|max:255',
            'contact_no'           => 'nullable|string|max:20',
            'contact_country_code' => 'nullable|string|max:8',
            'email'                => 'nullable|email|max:255',
            'address'              => 'nullable|string|max:1000',
            'date_of_joining'      => 'nullable|date',
            'status'               => 'nullable|in:active,inactive',
            'remarks'              => 'nullable|string|max:2000',
        ]);

        $investigationOfficer = DB::transaction(function () use ($request, $investigationOfficer, $data) {
        $investigationOfficer = $this->lockedOfficer($request, $investigationOfficer);
        if (!$request->user()->hasAnyRole(['admin', 'director_general', 'DG'])) {
            $data['circle'] = $request->user()->circle->name;
            $data['zone'] = $request->user()->circle?->zone?->name ?? $request->user()->zone?->name;
        }
        $investigationOfficer->update($data);

        // Keep linked portal user in sync
        if ($investigationOfficer->user) {
            $linked = User::query()->lockForUpdate()->findOrFail($investigationOfficer->user_id);
            $this->assertLinkedAccount($request->user(), $linked);
            $linked->update(array_filter([
                'name'        => $data['name'] ?? null,
                'designation' => $data['designation'] ?? null,
                'email'       => $data['email'] ?? null,
            ] + $this->resolveCircleZoneIds($investigationOfficer->fresh())));
        }
        return $investigationOfficer;
        }, 3);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Investigation Officer updated successfully',
                'data'    => $investigationOfficer->fresh()->load('user:id,name,email'),
            ]);
        }

        return redirect()->route('investigation-officers.index')
            ->with('success', 'Investigation Officer updated successfully');
    }

    public function grantAccess(Request $request, InvestigationOfficer $investigationOfficer)
    {
        $this->authorize('manageAccess', $investigationOfficer);

        if ($investigationOfficer->user_id) {
            return response()->json([
                'message' => 'Portal access already granted',
                'user'    => $investigationOfficer->user,
            ], 422);
        }

        $data = $request->validate([
            'email'    => 'required|email|max:255',
            'password' => StrongPassword::rules(false),
        ]);

        $password = $data['password'] ?? $this->generateStrongPassword();
        $user = $this->createOrUpdatePortalUser($investigationOfficer, $data['email'], $password);

        return response()->json([
            'message'  => 'Portal access granted successfully',
            'user'     => $user,
            'password' => $password,
        ])->header('Cache-Control', 'no-store');
    }

    public function sendOtp(Request $request, InvestigationOfficer $investigationOfficer)
    {
        $this->authorize('manageAccess', $investigationOfficer);

        if ($investigationOfficer->user_id) {
            return response()->json(['message' => 'Portal access already granted'], 422);
        }

        $data = $request->validate(['email' => 'required|email|max:255']);
        $email = strtolower($data['email']);
        $key = 'io-otp-send:' . hash('sha256', $email . ':' . $investigationOfficer->id);
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many code requests. Try again later.');
        RateLimiter::hit($key, 3600);
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($request, $investigationOfficer, $email, $otp) {
        $officer = $this->lockedOfficer($request, $investigationOfficer);
        abort_if($officer->user_id, 422, 'Portal access already granted.');
        abort_if(User::where('email', $email)->exists(), 422, 'This email is already assigned to an account.');
        Otp::where('email', $email)->where('officer_id', $officer->id)->where('type', 'io_access')
            ->whereNull('verified_at')->update(['expires_at' => now()]);
        Otp::create([
            'email'      => $email,
            'otp'        => '',
            'otp_hash'   => Hash::make($otp),
            'officer_id' => $officer->id,
            'type'       => 'io_access',
            'expires_at' => now()->addMinutes(5),
        ]);
        }, 3);

        $result = app(OtpMailService::class)->send($email, $otp, $investigationOfficer->name);

        if (!$result['ok']) {
            return response()->json([
                'message' => 'Secure code delivery is unavailable. Contact the administrator.',
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent to ' . $email . '. Inbox / Spam folder check karein.',
            'email'   => $email,
            'via'     => $result['via'],
        ]);
    }

    public function verifyOtp(Request $request, InvestigationOfficer $investigationOfficer)
    {
        $this->authorize('manageAccess', $investigationOfficer);

        {
            if ($investigationOfficer->user_id) {
                return response()->json([
                    'message' => 'Portal access already granted',
                    'user'    => $investigationOfficer->user,
                ], 422);
            }

            $data = $request->validate([
                'email' => 'required|email|max:255',
                'otp'   => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
            ]);
            $password = $this->generateStrongPassword();
            $user = DB::transaction(function () use ($request, $investigationOfficer, $data, $password) {
                $officer = $this->lockedOfficer($request, $investigationOfficer);
                abort_if($officer->user_id, 422, 'Portal access already granted.');
                $valid = Otp::where('email', strtolower($data['email']))->where('officer_id', $officer->id)
                    ->where('type', 'io_access')->whereNull('verified_at')->where('expires_at', '>', now())
                    ->latest('id')->lockForUpdate()->first();
                if (!$valid || !$valid->otp_hash || $valid->attempts >= 5) {
                    return null;
                }
                $valid->increment('attempts');
                if (!Hash::check($data['otp'], $valid->otp_hash)) {
                    return null;
                }
                $user = $this->createOrUpdatePortalUser($officer, $data['email'], $password);
                $valid->update(['verified_at' => now()]);
                return $user;
            }, 3);
            if (!$user) {
                return response()->json(['message' => 'Invalid or expired OTP'], 422);
            }

            return response()->json([
                'message'  => 'Portal access granted successfully',
                'user'     => $user,
                'password' => $password,
            ])->header('Cache-Control', 'no-store');
        }
    }

    public function resetPassword(Request $request, InvestigationOfficer $investigationOfficer)
    {
        $this->authorize('manageAccess', $investigationOfficer);

        if (!$investigationOfficer->user_id) {
            return response()->json(['message' => 'No portal access to reset password'], 422);
        }

        $user = $investigationOfficer->user;
        if (!$user) {
            return response()->json(['message' => 'Associated user not found'], 422);
        }

        $data = $request->validate([
            'password' => StrongPassword::rules(false),
        ]);

        $password = $data['password'] ?? $this->generateStrongPassword();
        $user = DB::transaction(function () use ($request, $investigationOfficer, $password) {
            $officer = $this->lockedOfficer($request, $investigationOfficer);
            $locked = User::query()->lockForUpdate()->findOrFail($officer->user_id);
            $this->assertLinkedAccount($request->user(), $locked);
            $locked->update(['password' => $password]);
            return $locked;
        }, 3);

        return response()->json([
            'message'  => 'Password reset successfully',
            'email'    => $user->email,
            'password' => $password,
        ])->header('Cache-Control', 'no-store');
    }

    public function revokeAccess(InvestigationOfficer $investigationOfficer)
    {
        $this->authorize('manageAccess', $investigationOfficer);

        if (!$investigationOfficer->user_id) {
            return response()->json(['message' => 'No portal access to revoke'], 422);
        }

        DB::transaction(function () use ($investigationOfficer) {
            $officer = $this->lockedOfficer(request(), $investigationOfficer);
            if ($officer->user_id) {
                $user = User::query()->lockForUpdate()->findOrFail($officer->user_id);
                $this->assertLinkedAccount(request()->user(), $user);
                $user->removeRole('investigation_officer');
                $user->update(['password' => bin2hex(random_bytes(32)), 'role' => null]);
            }
            $officer->update(['user_id' => null]);
        }, 3);

        return response()->json(['message' => 'Portal access removed successfully']);
    }

    private function lockedOfficer(Request $request, InvestigationOfficer $officer): InvestigationOfficer
    {
        $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
        abort_if($actor->isSuspended(), 403);
        abort_unless((int) $actor->security_version === (int) $request->user()->security_version, 409);
        $locked = InvestigationOfficer::query()->lockForUpdate()->findOrFail($officer->id);
        Gate::forUser($actor)->authorize('manageAccess', $locked);
        return $locked;
    }

    private function assertLinkedAccount(User $actor, User $target): void
    {
        if ($actor->hasAnyRole(['admin', 'director_general', 'DG'])) {
            return;
        }
        $roles = $target->roles()->pluck('name')->all();
        abort_unless($actor->circle_id && (int) $target->circle_id === (int) $actor->circle_id
            && $roles && !array_diff($roles, ['investigation_officer'])
            && (!$target->role || $target->role === 'investigation_officer'), 403,
            'You can only manage investigation officer accounts in your circle.');
    }

    public function destroy(InvestigationOfficer $investigationOfficer)
    {
        $this->authorize('delete', $investigationOfficer);

        if ($investigationOfficer->user) {
            $investigationOfficer->user->removeRole('investigation_officer');
        }

        $investigationOfficer->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Investigation Officer deleted successfully']);
        }

        return redirect()->route('investigation-officers.index')
            ->with('success', 'Investigation Officer deleted successfully');
    }
}

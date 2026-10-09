<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use App\Rules\StrongPassword;

class UserController extends Controller
{
    public const CIRCLE_MANAGEABLE_ROLES = [
        'investigation_officer',
        'enquiry_officer',
        'moharrar',
        'operator',
        'front_desk_officer',
        'ad_administration',
        'reader_branch',
        'verification_officer',
    ];

    public function index(Request $request)
    {
        $actor = $request->user();
        $query = User::with('roles', 'permissions', 'circle:id,name,code', 'zone:id,name,code')->latest();

        // Zonal / Regional Head: sees users in their zone
        if ($actor && $actor->isZonalHead()) {
            $effectiveZoneId = $actor->zone_id ?: $actor->circle?->zone_id;
            $query->where(function ($q) use ($effectiveZoneId) {
                $q->where('zone_id', $effectiveZoneId)
                  ->orWhereHas('circle', fn ($cq) => $cq->where('zone_id', $effectiveZoneId));
            });
        }
        // Regional Circle Incharge: strictly limited to users in their circle
        elseif ($actor && $actor->hasRole('circle_incharge') && !$actor->seesAllData()) {
            $circleId = $actor->circle_id ?: 0;
            $query->where('circle_id', $circleId);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        if ($roleFilter = $request->query('role')) {
            $query->where(function ($q) use ($roleFilter) {
                $q->where('role', $roleFilter)
                  ->orWhereHas('roles', fn ($rq) => $rq->where('name', $roleFilter));
            });
        }

        $perPage = min(100, max(10, (int) $request->query('per_page', 15)));
        $users = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json($users);
        }

        return view('users.index', compact('users'));
    }

    public function show(Request $request, User $user)
    {
        $actor = $request->user();
        if ($actor && !$actor->seesAllData()) {
            if (!$actor->canAccessCircle($user->circle_id)) {
                return response()->json(['message' => 'Unauthorized. User belongs to another circle.'], 403);
            }
        }

        $user->load('roles', 'permissions', 'circle', 'zone');
        return response()->json($user);
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $isCi = $actor && $actor->hasRole('circle_incharge') && !$actor->seesAllData();

        $rules = [
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => StrongPassword::rules(),
            'role'        => 'required|string|exists:roles,name',
            'designation' => 'nullable|string|max:255',
            'circle_id'   => 'nullable|exists:circles,id',
            'zone_id'     => 'nullable|exists:zones,id',
        ];

        $data = $request->validate($rules, [
            'password.regex' => 'Password must include uppercase, lowercase, a number, and a special character (@$!%*#?&).',
        ]);

        if ($isCi) {
            // Circle Incharge can only create specific field/station roles
            if (!in_array($data['role'], self::CIRCLE_MANAGEABLE_ROLES, true)) {
                return response()->json([
                    'message' => 'Circle Incharge can only create station staff: Front Desk Officer, VO, EO, IO, Moharrar, Reader Branch, AD Admin.',
                ], 403);
            }

            // Strictly lock circle to Circle Incharge's own circle
            $data['circle_id'] = $actor->circle_id;
            $data['zone_id'] = $actor->circle?->zone_id ?? $actor->zone_id;
        }

        $user = DB::transaction(function () use ($request, $data) {
        $actor = $this->lockedActor($request);
        $isCi = $actor->hasRole('circle_incharge') && !$actor->hasAnyRole(['admin', 'director_general']);
        if ($isCi) {
            abort_unless($actor->circle_id && in_array($data['role'], self::CIRCLE_MANAGEABLE_ROLES, true), 403);
            $data['circle_id'] = $actor->circle_id;
            $data['zone_id'] = $actor->circle?->zone_id ?? $actor->zone_id;
        }
        $user = User::create([
            'name'        => $data['name'],
            'email'       => strtolower($data['email']),
            'password'    => Hash::make($data['password']),
            'role'        => $data['role'],
            'designation' => $data['designation'] ?? null,
            'circle_id'   => $data['circle_id'] ?? null,
            'zone_id'     => $data['zone_id'] ?? null,
        ]);

        $user->assignRole($data['role']);
        return $user;
        }, 3);

        return response()->json(['message' => 'User created successfully', 'user' => $user->load('roles', 'permissions', 'circle', 'zone')], 201);
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        $isCi = $actor && $actor->hasRole('circle_incharge') && !$actor->seesAllData();

        if ($isCi) {
            if ((int) $user->circle_id !== (int) $actor->circle_id) {
                return response()->json(['message' => 'Unauthorized. You can only edit officers in your circle.'], 403);
            }
            if (!in_array($user->role, self::CIRCLE_MANAGEABLE_ROLES, true) && !$user->hasAnyRole(self::CIRCLE_MANAGEABLE_ROLES)) {
                return response()->json(['message' => 'Unauthorized. You cannot modify high-level leadership accounts.'], 403);
            }
        }

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'    => StrongPassword::rules(false),
            'role'        => 'required|string|exists:roles,name',
            'designation' => 'nullable|string|max:255',
            'circle_id'   => 'nullable|exists:circles,id',
            'zone_id'     => 'nullable|exists:zones,id',
        ]);

        if ($isCi) {
            if (!in_array($data['role'], self::CIRCLE_MANAGEABLE_ROLES, true)) {
                return response()->json([
                    'message' => 'Circle Incharge can only assign station roles: Front Desk Officer, VO, EO, IO, Moharrar, Reader Branch, AD Admin.',
                ], 403);
            }
            $data['circle_id'] = $actor->circle_id;
            $data['zone_id'] = $actor->circle?->zone_id ?? $actor->zone_id;
        }

        $updateData = [
            'name'        => $data['name'],
            'email'       => strtolower($data['email']),
            'role'        => $data['role'],
            'designation' => $data['designation'] ?? null,
            'circle_id'   => $data['circle_id'] ?? null,
            'zone_id'     => $data['zone_id'] ?? null,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user = $this->mutate($request, $user, function (User $locked, User $actor) use ($updateData, $data) {
            if (!$actor->hasAnyRole(['admin', 'director_general'])) {
                abort_unless(in_array($data['role'], self::CIRCLE_MANAGEABLE_ROLES, true), 403);
                $updateData['circle_id'] = $actor->circle_id;
                $updateData['zone_id'] = $actor->circle?->zone_id ?? $actor->zone_id;
            }
            $oldRoles = $locked->roles()->pluck('name')->sort()->values()->all();
            $oldVersion = (int) $locked->security_version;
            $locked->update($updateData);
            $locked->syncRoles([$data['role']]);
            if ($oldRoles !== [$data['role']] && (int) $locked->security_version === $oldVersion) {
                $locked->invalidateAuthentication();
            }
        });

        return response()->json(['message' => 'User updated successfully', 'user' => $user->fresh()->load('roles', 'permissions', 'circle', 'zone')]);
    }

    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();
        if ($user->id === $actor->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 422);
        }

        $isCi = $actor && $actor->hasRole('circle_incharge') && !$actor->seesAllData();
        if ($isCi) {
            if ((int) $user->circle_id !== (int) $actor->circle_id) {
                return response()->json(['message' => 'Unauthorized. You can only delete officers in your circle.'], 403);
            }
            if (!in_array($user->role, self::CIRCLE_MANAGEABLE_ROLES, true) && !$user->hasAnyRole(self::CIRCLE_MANAGEABLE_ROLES)) {
                return response()->json(['message' => 'Unauthorized. You cannot delete this user.'], 403);
            }
        }

        $this->mutate($request, $user, function (User $locked, User $actor) {
            abort_if($locked->id === $actor->id, 422, 'Cannot delete your own account');
            $locked->delete();
        });

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function grantPermission(Request $request, User $user)
    {
        try {
            $data = $request->validate([
                'permission' => 'required|string',
            ]);

            $user = $this->mutate($request, $user, function (User $locked, User $actor) use ($data) {
                abort_unless($actor->hasRole('admin'), 403);
                $permission = Permission::where('name', $data['permission'])->where('guard_name', 'web')->firstOrFail();
                if (!$locked->permissions()->whereKey($permission->id)->exists()) {
                    $locked->givePermissionTo($permission);
                    $locked->invalidateAuthentication();
                }
            });

            return response()->json([
                'message' => "Permission '{$data['permission']}' granted to {$user->name}",
                'user' => $user->fresh()->load('roles', 'permissions'),
            ]);
        } catch (\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('grantPermission failed: '.get_class($e));
            return response()->json(['message' => 'Could not grant permission.'], 500);
        }
    }

    public function revokePermission(Request $request, User $user)
    {
        try {
            $data = $request->validate([
                'permission' => 'required|string',
            ]);

            $permission = Permission::where('name', $data['permission'])->where('guard_name', 'web')->first();
            if ($permission) {
                $user = $this->mutate($request, $user, function (User $locked, User $actor) use ($permission) {
                    abort_unless($actor->hasRole('admin'), 403);
                    if ($locked->permissions()->whereKey($permission->id)->exists()) {
                        $locked->revokePermissionTo($permission);
                        $locked->invalidateAuthentication();
                    }
                });
            }

            return response()->json([
                'message' => "Permission '{$data['permission']}' revoked from {$user->name}",
                'user' => $user->fresh()->load('roles', 'permissions'),
            ]);
        } catch (\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('revokePermission failed: '.get_class($e));
            return response()->json(['message' => 'Could not revoke permission.'], 500);
        }
    }

    public function resetPassword(Request $request, User $user)
    {
        $actor = $request->user();
        $isCi = $actor && $actor->hasRole('circle_incharge') && !$actor->seesAllData();
        if ($isCi) {
            if ((int) $user->circle_id !== (int) $actor->circle_id) {
                return response()->json(['message' => 'Unauthorized. You can only reset password for officers in your circle.'], 403);
            }
        }

        $data = $request->validate([
            'password' => StrongPassword::rules(),
        ]);

        $user = $this->mutate($request, $user, function (User $locked) use ($data) {
            $locked->update(['password' => $data['password']]);
        });

        return response()->json(['message' => "Password reset successfully for {$user->name}"]);
    }

    private function lockedActor(Request $request): User
    {
        $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
        abort_if($actor->isSuspended(), 403);
        abort_unless($actor->hasAnyRole(['admin', 'director_general', 'circle_incharge']), 403);
        abort_unless((int) $actor->security_version === (int) $request->user()->security_version, 409,
            'Your account changed. Please sign in again.');
        return $actor;
    }

    private function mutate(Request $request, User $target, callable $operation): User
    {
        return DB::transaction(function () use ($request, $target, $operation) {
            $actor = $this->lockedActor($request);
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);
            if (!$actor->hasAnyRole(['admin', 'director_general'])) {
                $roles = $locked->roles()->pluck('name')->all();
                abort_unless($actor->circle_id && (int) $locked->circle_id === (int) $actor->circle_id
                    && $roles && !array_diff($roles, self::CIRCLE_MANAGEABLE_ROLES)
                    && (!$locked->role || in_array($locked->role, self::CIRCLE_MANAGEABLE_ROLES, true)),
                    403, 'You can only manage station staff in your circle.');
            }
            $operation($locked, $actor);
            return $locked;
        }, 3);
    }
}

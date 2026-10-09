<?php

namespace App\Http\Controllers\Forensic;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Rules\StrongPassword;

class ForensicUserController extends Controller
{
    private function generateStrongPassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function forensicRoles(): array
    {
        return User::FORENSIC_ROLES;
    }

    public function index(Request $request)
    {
        $users = User::with('roles', 'permissions')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $this->forensicRoles()))
            ->latest()
            ->paginate(15);

        return response()->json($users);
    }

    public function show(User $user)
    {
        $this->authorizeForensic($user);
        return response()->json($user->load('roles', 'permissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email',
            'password'         => StrongPassword::rules(false),
            'generate_password'=> 'sometimes|boolean',
            'role'             => ['required', Rule::in($this->forensicRoles())],
            'designation'      => 'nullable|string|max:255',
        ], [
            'password.regex' => 'Password must include uppercase, lowercase, a number, and a special character (@$!%*#?&).',
        ]);

        $generated = !empty($data['generate_password']) || empty($data['password']);
        $password = $generated ? $this->generateStrongPassword() : $data['password'];

        $user = DB::transaction(function () use ($request, $data, $password) {
        $this->lockedActor($request);
        $user = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => Hash::make($password),
            'role'        => $data['role'],
            'designation' => $data['designation'] ?? null,
        ]);

        $user->assignRole($data['role']);
        return $user;
        }, 3);

        $payload = [
            'message' => 'Forensic user created successfully',
            'user' => $user->load('roles', 'permissions'),
        ];

        // Credentials are shown only once so the admin can hand them to the user
        if ($generated) {
            $payload['credentials'] = [
                'email'    => $user->email,
                'password' => $password,
            ];
        }

        return response()->json($payload, 201)->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeForensic($user);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'    => StrongPassword::rules(false),
            'role'        => ['required', Rule::in($this->forensicRoles())],
            'designation' => 'nullable|string|max:255',
        ]);

        $updateData = [
            'name'        => $data['name'],
            'email'       => $data['email'],
            'role'        => $data['role'],
            'designation' => $data['designation'] ?? null,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user = $this->mutate($request, $user, function (User $locked) use ($updateData, $data) {
            $oldRoles = $locked->roles()->pluck('name')->sort()->values()->all();
            $oldVersion = (int) $locked->security_version;
            $locked->update($updateData);
            $locked->syncRoles([$data['role']]);
            if ($oldRoles !== [$data['role']] && (int) $locked->security_version === $oldVersion) {
                $locked->invalidateAuthentication();
            }
        });

        return response()->json([
            'message' => 'Forensic user updated successfully',
            'user' => $user->fresh()->load('roles', 'permissions'),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 422);
        }

        $this->authorizeForensic($user);

        $this->mutate($request, $user, function (User $locked) use ($request) {
            abort_if($locked->id === $request->user()->id, 422, 'Cannot delete your own account');
            $locked->delete();
        });

        return response()->json(['message' => 'Forensic user deleted successfully']);
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeForensic($user);

        $data = $request->validate([
            'password' => StrongPassword::rules(),
        ], [
            'password.regex' => 'Password must include uppercase, lowercase, a number, and a special character (@$!%*#?&).',
        ]);

        $user = $this->mutate($request, $user, fn (User $locked) => $locked->update(['password' => $data['password']]));

        return response()->json(['message' => "Password reset successfully for {$user->name}"]);
    }

    public function generatePassword(Request $request, User $user)
    {
        $this->authorizeForensic($user);

        $password = $this->generateStrongPassword();
        $user = $this->mutate($request, $user, fn (User $locked) => $locked->update(['password' => $password]));

        return response()->json([
            'message' => "New credentials generated for {$user->name}",
            'credentials' => [
                'email'    => $user->email,
                'password' => $password,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function stats()
    {
        $roles = $this->forensicRoles();
        $users = User::whereHas('roles', fn ($q) => $q->whereIn('name', $roles));
        $total = (clone $users)->count();
        $byRole = [];

        foreach ($roles as $role) {
            $byRole[$role] = (clone $users)->whereHas('roles', fn ($q) => $q->where('name', $role))->count();
        }

        return response()->json([
            'total_users' => $total,
            'by_role'     => $byRole,
            'roles'       => $roles,
        ]);
    }

    private function authorizeForensic(User $user): void
    {
        $roles = $user->roles()->pluck('name')->all();
        if (!$roles || array_diff($roles, $this->forensicRoles())
            || ($user->role && !in_array($user->role, $this->forensicRoles(), true))) {
            abort(404);
        }
    }

    private function lockedActor(Request $request): User
    {
        $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
        abort_unless(!$actor->isSuspended() && $actor->hasRole('admin_forensic'), 403);
        abort_unless((int) $actor->security_version === (int) $request->user()->security_version, 409,
            'Your account changed. Please sign in again.');
        return $actor;
    }

    private function mutate(Request $request, User $target, callable $operation): User
    {
        return DB::transaction(function () use ($request, $target, $operation) {
            $this->lockedActor($request);
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);
            $this->authorizeForensic($locked);
            $operation($locked);
            return $locked;
        }, 3);
    }

    public function rolesList()
    {
        return response()->json([
            'roles' => $this->forensicRoles(),
        ]);
    }
}

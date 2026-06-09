<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = strtolower(trim($credentials['email']));

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $this->ensureTenantAccess($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant setup is required before accessing POS.',
                'requires_tenant_setup' => true,
            ], 409);
        }

        $plainTextToken = $user->createToken('api-mobile')->plainTextToken;
        
        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'user' => $this->transformUser($user),
            'roles' => [],
            'permissions' => [],
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->transformUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $currentToken = $request->user()?->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        return response()->json(['success' => true]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'tenant_id' => $validated['tenant_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'user' => $this->transformUser($user),
            'roles' => [],
        ], 201);
    }

    public function validateToken(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Token valid',
            'user' => $this->transformUser($request->user()),
            'roles' => [],
            'permissions' => [],
        ]);
    }

    public function showInvitation(string $token): JsonResponse
    {
        $user = User::where('invitation_token', $token)
                    ->where('invitation_expires_at', '>', Carbon::now())
                    ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid or expired invitation token.',
            ], 404);
        }

        return response()->json([
            'email' => $user->email,
            'name' => $user->name,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function completeInvitation(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('invitation_token', $token)
                    ->where('invitation_expires_at', '>', Carbon::now())
                    ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid or expired invitation token.',
            ], 404);
        }

        $user->update([
            'name' => $validated['name'],
            'password' => Hash::make($validated['password']),
            'invitation_token' => null,
            'invitation_expires_at' => null,
            'email_verified_at' => Carbon::now(),
        ]);

        $plainTextToken = $user->createToken('api-mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Undangan selesai. Login otomatis berhasil.',
            'user' => $this->transformUser($user),
            'roles' => [],
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    private function transformUser(User $user): array
    {
        $user->loadMissing('detail');
        $effectiveRole = $this->resolveEffectiveRole($user);
        $canApproveRefunds = in_array($effectiveRole, ['manager', 'owner'], true);

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'role' => $effectiveRole,
            'base_role' => $user->role,
            'can_approve_refunds' => $canApproveRefunds,
            'manager_id' => $user->manager_id,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function resolveEffectiveRole(User $user): string
    {
        $tenantId = $user->tenant_id;
        $userKey = (string) ($user->uuid ?: $user->id);
        $role = strtolower((string) ($user->role ?? 'staff'));

        if ($tenantId && Tenant::where('id', $tenantId)->where('owner_id', $userKey)->exists()) {
            return 'owner';
        }

        if ($tenantId && Schema::connection('mysql_auth')->hasTable('tenant_user')) {
            $tenantRole = DB::connection('mysql_auth')->table('tenant_user')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userKey)
                ->value('role');

            if (is_string($tenantRole) && $tenantRole !== '') {
                $role = strtolower($tenantRole);
            }
        }

        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('owner')) {
                return 'owner';
            }
            if ($user->hasRole('manager')) {
                return 'manager';
            }
        }

        return $role;
    }

    private function ensureTenantAccess(User $user): bool
    {
        if ($user->tenant_id) {
            return true;
        }

        $tenant = Tenant::where('owner_id', $user->uuid)->first();

        if (! $tenant) {
            $tenant = $user->tenants()->first();
        }

        if (! $tenant) {
            return false;
        }

        DB::transaction(function () use ($user, $tenant) {
            $user->tenant_id = $tenant->id;
            $user->save();
        });

        $user->refresh();
        $user->load('detail');

        return (bool) $user->tenant_id;
    }
}

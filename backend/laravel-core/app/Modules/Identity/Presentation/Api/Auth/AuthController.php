<?php

namespace App\Modules\Identity\Presentation\Api\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notification\Application\Services\AppNotificationService;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function registerCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'religion' => ['nullable', 'string', 'max:100'],
            'allergies' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'] ?? null,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'customer',
            ]);

            CustomerProfile::create([
                'user_id' => $user->id,
                'phone_number' => $validated['phone_number'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'allergies' => $validated['allergies'] ?? null,
                'status' => 'active',
            ]);

            return $user->load('customerProfile');
        });

        if ($request->hasSession()) {
            Auth::guard('web')->login($user, false);
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Customer registration successful.',
            'user' => $user,
        ], 201);
    }

    public function registerProvider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'country_code' => ['required', 'string', 'max:15'],
            'phone_number' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'service_category' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $phoneNumber = preg_replace('/\s+/', '', $validated['phone_number']);

            $user = User::create([
                'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'provider',
            ]);

            ProviderProfile::create([
                'user_id' => $user->id,
                'phone_number' => trim($validated['country_code'] . ' ' . $phoneNumber),
                'category' => $validated['service_category'] ?? null,
                'status' => 'inactive',
                'document_status' => 'pending',
            ]);

            return $user->load('providerProfile');
        });

        app(AppNotificationService::class)->createForUsers(
            app(AppNotificationService::class)->adminRecipients(),
            'provider.registered',
            'Provider baru mendaftar',
            ($user->name ?: 'Provider') . ' menunggu review admin.',
            route('admin.providers.show', $user->id),
            [
                'provider_id' => (int) $user->id,
            ],
            (int) $user->id
        );

        if ($request->hasSession()) {
            Auth::guard('provider')->login($user);
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Pendaftaran mitra berhasil. Lengkapi dokumen verifikasi untuk membuka seluruh menu.',
            'user' => $user,
            'redirect_url' => route('provider.verification', [], false),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'role' => ['nullable', Rule::in(['admin', 'provider', 'customer'])],
            'remember' => ['nullable', 'boolean'],
            'issue_token' => ['nullable', 'boolean'],
        ]);

        $role = $validated['role'] ?? null;
        $user = User::where('email', $validated['email'])
            ->when($role, fn ($query, $role) => $query->where('role', $role))
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email or password is incorrect.',
            ], 401);
        }

        if ($user->role === 'provider') {
            $profile = ProviderProfile::firstOrCreate(
                ['user_id' => ProviderMenuAccess::providerOwnerId($user)],
                ['status' => 'inactive', 'document_status' => 'pending']
            );

            if ($profile->status !== 'active' && $profile->document_status === 'verified') {
                return response()->json([
                    'message' => 'Akun provider sedang dinonaktifkan oleh admin.',
                ], 403);
            }
        }

        if ($user->role === 'customer') {
            $profile = CustomerProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['status' => 'active']
            );

            if ($profile->status !== 'active') {
                return response()->json([
                    'message' => 'The customer account is inactive.',
                ], 403);
            }
        }

        if ($request->hasSession()) {
            $sessionGuard = $user->role === 'provider' ? 'provider' : 'web';
            Auth::guard($sessionGuard)->login($user, $request->boolean('remember'));
            $request->session()->regenerate();
        }

        $token = $user->role !== 'customer' && $request->boolean('issue_token')
            ? $user->createToken('api-token')->plainTextToken
            : null;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->load(['customerProfile', 'providerProfile']),
            'token' => $token,
            'redirect_url' => $user->role === 'provider'
                ? route($user->providerProfile?->document_status === 'verified' ? 'provider.dashboard' : 'provider.verification', [], false)
                : null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()?->load(['customerProfile', 'providerProfile']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }
}

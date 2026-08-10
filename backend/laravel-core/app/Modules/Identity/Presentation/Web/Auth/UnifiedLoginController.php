<?php

namespace App\Modules\Identity\Presentation\Web\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Support\FrontendUrl;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UnifiedLoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Admin Auth
    |--------------------------------------------------------------------------
    */
    public function showLoginForm()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $credentials = $request->only('email', 'password');

        $adminGuard = Auth::guard('admin');

        if (! $adminGuard->attempt($credentials)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Email atau password salah.',
                ]);
        }

        $request->session()->regenerate();

        if ($adminGuard->user()?->role !== 'admin') {
            $adminGuard->logout();

            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors([
                    'email' => 'Akun ini bukan akun admin.',
                ]);
        }

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /*
    |--------------------------------------------------------------------------
    | Provider Auth (Landing)
    |--------------------------------------------------------------------------
    */
    public function providerSignin(Request $request)
    {
        $request->merge([
            'login_email' => $request->input('login_email', $request->input('email')),
            'login_password' => $request->input('login_password', $request->input('password')),
        ]);

        $validator = Validator::make($request->all(), [
            'login_email' => ['required', 'email'],
            'login_password' => ['required', 'string'],
        ], [
            'login_email.required' => 'Email wajib diisi.',
            'login_email.email' => 'Format email tidak valid.',
            'login_password.required' => 'Password wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->redirectToProviderFrontend(
                $validator->errors()->first() ?: 'Data login belum lengkap.',
                $request->login_email ?? ''
            );
        }

        $remember = $request->boolean('remember');
        $candidate = User::query()
            ->where('email', $request->login_email)
            ->where('role', 'provider')
            ->first();

        if (! $candidate) {
            return $this->redirectToProviderFrontend('Email atau password salah.', $request->login_email ?? '');
        }

        $isBranchAccount = ! ProviderMenuAccess::isProviderOwner($candidate);
        $guardName = $isBranchAccount ? 'provider_branch' : 'provider';
        $providerGuard = Auth::guard($guardName);
        $credentials = [
            'email' => $request->login_email,
            'password' => $request->login_password,
            'role' => 'provider',
        ];

        if (! $providerGuard->attempt($credentials, $remember)) {
            return $this->redirectToProviderFrontend('Email atau password salah.', $request->login_email ?? '');
        }

        $request->session()->regenerate();

        $user = $providerGuard->user();

        if (! $user || $user->role !== 'provider' || ProviderMenuAccess::isProviderOwner($user) === $isBranchAccount) {
            $providerGuard->logout();

            $request->session()->regenerateToken();

            return $this->redirectToProviderFrontend('Akun ini bukan akun provider.', $request->login_email ?? '');
        }

        $providerProfile = ProviderProfile::firstOrCreate(
            ['user_id' => ProviderMenuAccess::providerOwnerId($user)],
            [
                'status' => 'inactive',
                'document_status' => 'pending',
            ]
        );

        if ($providerProfile->status !== 'active' && $providerProfile->document_status === 'verified') {
            $providerGuard->logout();

            $request->session()->regenerateToken();

            return $this->redirectToProviderFrontend('Akun provider kamu belum diaktifkan oleh admin.', $request->login_email ?? '');
        }

        return redirect()->to(route(
            $isBranchAccount ? 'provider-branch.dashboard' : 'provider.dashboard',
            [],
            false
        ));
    }

    public function providerLogout(Request $request)
    {
        Auth::guard($request->routeIs('provider-branch.*') ? 'provider_branch' : 'provider')->logout();

        $request->session()->regenerateToken();

        return redirect()->away($this->providerFrontendUrl());
    }

    // Customer auth untuk React memakai App\Modules\Identity\Presentation\Api\Auth\AuthController.

    private function redirectToProviderFrontend(string $message, string $email = '')
    {
        $url = rtrim($this->providerFrontendUrl(), '/') . '/register';
        $separator = str_contains($url, '?') ? '&' : '?';

        return redirect()->away($url . $separator . http_build_query([
            'mode' => 'login',
            'login' => 'failed',
            'login_error' => $message,
            'login_email' => $email,
        ]));
    }

    private function providerFrontendUrl(): string
    {
        return FrontendUrl::provider(request());
    }
}

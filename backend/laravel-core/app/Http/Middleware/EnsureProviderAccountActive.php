<?php

namespace App\Http\Middleware;

use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $providerGuard = Auth::guard($request->routeIs('provider-branch.*') ? 'provider_branch' : 'provider');
        $user = $providerGuard->user() ?: Auth::user();

        if (!$user) {
            return redirect()->route('provider.login');
        }

        if ($user->role !== 'provider') {
            return $next($request);
        }

        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => ProviderMenuAccess::providerOwnerId($user)],
            [
                'status' => 'inactive',
                'document_status' => 'pending',
            ]
        );

        if ($profile->status !== 'active' && $profile->document_status === 'verified') {
            $providerGuard->logout();

            $request->session()->regenerateToken();

            return redirect()
                ->route('provider.landing')
                ->with('error', 'Akun provider belum diaktifkan oleh admin.');
        }

        return $next($request);
    }
}

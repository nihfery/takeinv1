<?php

namespace App\Http\Middleware;

use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderDocumentVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::guard($request->routeIs('provider-branch.*') ? 'provider_branch' : 'provider')->user() ?: Auth::user();

        if (!$user) {
            return redirect()->route('provider.login');
        }

        if ($user->role !== 'provider') {
            return $next($request);
        }

        $profile = ProviderProfile::where('user_id', ProviderMenuAccess::providerOwnerId($user))->first();

        if (!$profile) {
            return provider_route_redirect('provider.profile.edit')
                ->with('error', 'Lengkapi profil Anda terlebih dahulu.');
        }

        if ($profile->document_status !== 'verified') {
            return provider_route_redirect('provider.verification')
                ->with('error', 'Selesaikan verifikasi mitra terlebih dahulu agar semua menu dapat digunakan.');
        }

        return $next($request);
    }
}

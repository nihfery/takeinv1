<?php

namespace App\Http\Middleware;

use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderMenuPermission
{
    public function handle(Request $request, Closure $next, ?string $menuKey = null): Response
    {
        $user = Auth::guard($request->routeIs('provider-branch.*') ? 'provider_branch' : 'provider')->user() ?: Auth::user();

        if (! $user || $user->role !== 'provider') {
            return $next($request);
        }

        $menuKey = $menuKey ?: ProviderMenuAccess::routeMenuKey($request->route()?->getName());

        if (! ProviderMenuAccess::userCanAccess($user, $menuKey)) {
            return redirect()
                ->route('provider.dashboard')
                ->with('error', 'Role akun ini belum diberi akses ke menu tersebut.');
        }

        return $next($request);
    }
}

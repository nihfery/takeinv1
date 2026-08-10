<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

if (! function_exists('provider_route_name')) {
    function provider_route_name(string $name, ?bool $branch = null): string
    {
        if (! str_starts_with($name, 'provider.')) {
            return $name;
        }

        if ($branch === null) {
            $currentRouteName = request()->route()?->getName() ?? '';
            $branch = str_starts_with($currentRouteName, 'provider-branch.')
                || (
                    ! str_starts_with($currentRouteName, 'provider.')
                    && Auth::guard('provider_branch')->check()
                    && ! Auth::guard('provider')->check()
                );
        }

        if (! $branch) {
            return $name;
        }

        $branchName = 'provider-branch.' . substr($name, strlen('provider.'));

        return Route::has($branchName) ? $branchName : $name;
    }
}

if (! function_exists('provider_route')) {
    function provider_route(string $name, mixed $parameters = [], bool $absolute = true, ?bool $branch = null): string
    {
        return route(provider_route_name($name, $branch), $parameters, $absolute);
    }
}

if (! function_exists('provider_route_redirect')) {
    function provider_route_redirect(string $name, mixed $parameters = [], int $status = 302, array $headers = [], ?bool $branch = null): \Illuminate\Http\RedirectResponse
    {
        return redirect()->to(provider_route($name, $parameters, true, $branch), $status, $headers);
    }
}

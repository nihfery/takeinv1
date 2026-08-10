<?php

namespace App\Http\Middleware;

use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderApiAccess
{
    private const OPERATIONAL_REQUIREMENTS = [
        'services',
        'staffs',
        'branch',
    ];

    public function handle(Request $request, Closure $next, ?string $requirement = null): Response
    {
        $user = $request->user();

        abort_unless($user && $user->role === 'provider', 403, 'Access denied.');

        if ($requirement === 'owner') {
            abort_unless(
                ProviderMenuAccess::isProviderOwner($user),
                403,
                'Only the main provider account can perform this action.'
            );
        } elseif ($requirement !== null) {
            if (in_array($requirement, self::OPERATIONAL_REQUIREMENTS, true)) {
                $profile = ProviderMenuAccess::providerProfile($user);

                abort_unless(
                    $profile?->status === 'active' && $profile->document_status === 'verified',
                    403,
                    'The provider account must be active and verified.'
                );
            }

            abort_unless(
                ProviderMenuAccess::userCanAccess($user, $requirement),
                403,
                'This provider account does not have permission for the requested resource.'
            );
        }

        return $next($request);
    }
}

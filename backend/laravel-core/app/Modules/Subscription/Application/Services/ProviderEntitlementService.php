<?php

namespace App\Modules\Subscription\Application\Services;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;

class ProviderEntitlementService
{
    /**
     * Operational access is available to every verified provider. Paid-plan
     * feature entitlements can be layered here later by plan.
     */
    public function checkResourceLimit(User $providerUser, string $resourceKey, int $requestedCount = 1): array
    {
        if ($providerUser->role !== 'provider') {
            return ['allowed' => false, 'reason' => 'Invalid role'];
        }

        $providerOwnerId = ProviderMenuAccess::providerOwnerId($providerUser);
        $profile = User::find($providerOwnerId)?->providerProfile;
        
        if (!$profile) {
            return ['allowed' => false, 'reason' => 'Profile not found'];
        }

        return [
            'allowed' => true,
            'is_unlimited' => true,
            'limit' => null,
            'current_count' => null,
            'remaining' => null,
        ];
    }

    public function canUseSupportChat(User $providerUser): bool
    {
        return $providerUser->role === 'provider'
            && ProviderMenuAccess::hasVerifiedDocuments($providerUser);
    }
}

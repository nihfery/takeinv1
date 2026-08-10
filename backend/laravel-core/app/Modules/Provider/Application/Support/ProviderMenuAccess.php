<?php

namespace App\Modules\Provider\Application\Support;

use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Str;

class ProviderMenuAccess
{
    private const ALWAYS_ALLOWED_KEYS = [
        'dashboard',
    ];

    private static array $permissionCache = [];

    public static function sections(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    [
                        'key' => 'dashboard',
                        'label' => 'Dashboard',
                        'description' => 'Overview of provider performance, bookings, revenue, and activity.',
                    ],
                ],
            ],
            [
                'title' => 'Appointments',
                'items' => [
                    [
                        'key' => 'bookings',
                        'label' => 'Bookings',
                        'description' => 'View appointments and move each booking through its operational status.',
                    ],
                    [
                        'key' => 'calendar',
                        'label' => 'Calendar',
                        'description' => 'View every appointment in a monthly calendar and open complete daily details.',
                    ],
                    [
                        'key' => 'queue',
                        'label' => 'Queue',
                        'description' => 'Manage the active front-desk queue and call customers.',
                    ],
                    [
                        'key' => 'walk_in',
                        'label' => 'Walk-in',
                        'description' => 'Create appointments for offline or walk-in customers.',
                    ],
                ],
            ],
            [
                'title' => 'Business',
                'items' => [
                    [
                        'key' => 'services',
                        'label' => 'Services',
                        'description' => 'Manage services, prices, durations, galleries, and service status.',
                    ],
                    [
                        'key' => 'branch',
                        'label' => 'Locations',
                        'description' => 'Manage branch locations and operating information.',
                    ],
                    [
                        'key' => 'staffs',
                        'label' => 'Team',
                        'description' => 'Manage professional profiles, contacts, locations, and status.',
                    ],
                    [
                        'key' => 'staff_skills',
                        'label' => 'Skills',
                        'description' => 'Define which services each professional can perform.',
                    ],
                    [
                        'key' => 'staff_schedules',
                        'label' => 'Work Schedules',
                        'description' => 'Manage professional working days and hours.',
                    ],
                ],
            ],
            [
                'title' => 'Customers',
                'items' => [
                    [
                        'key' => 'customers',
                        'label' => 'Customer Directory',
                        'description' => 'View customers who have booked with this business.',
                    ],
                    [
                        'key' => 'reviews',
                        'label' => 'Reviews',
                        'description' => 'View customer ratings, reviews, and feedback.',
                    ],
                ],
            ],
            [
                'title' => 'Finance',
                'items' => [
                    [
                        'key' => 'payments',
                        'label' => 'Transactions',
                        'description' => 'View transactions, payment statuses, and booking invoices.',
                    ],
                ],
            ],
            [
                'title' => 'Communication',
                'items' => [
                    [
                        'key' => 'chat',
                        'label' => 'Support Chat',
                        'description' => 'Access real-time conversations with internal or admin support.',
                    ],
                    [
                        'key' => 'notifications',
                        'label' => 'Notifications',
                        'description' => 'Access notifications and operational alerts.',
                    ],
                    [
                        'key' => 'tickets',
                        'label' => 'Support Help',
                        'description' => 'View FAQs and submit support chat tickets.',
                    ],
                ],
            ],
            [
                'title' => 'Account',
                'items' => [
                    [
                        'key' => 'roles_permissions',
                        'label' => 'Roles & Permissions',
                        'description' => 'Manage branch roles and granted menu access.',
                    ],
                    [
                        'key' => 'profile',
                        'label' => 'My Profile',
                        'description' => 'Update account profile, documents, and password.',
                    ],
                ],
            ],
        ];
    }

    public static function keys(): array
    {
        return collect(self::sections())
            ->flatMap(fn (array $section) => $section['items'])
            ->pluck('key')
            ->all();
    }

    public static function labels(): array
    {
        return collect(self::sections())
            ->flatMap(fn (array $section) => $section['items'])
            ->mapWithKeys(fn (array $item) => [$item['key'] => $item['label']])
            ->all();
    }

    public static function userCanAccess(?User $user, ?string $menuKey): bool
    {
        if (! $menuKey || in_array($menuKey, self::ALWAYS_ALLOWED_KEYS, true)) {
            return true;
        }

        if (! $user || $user->role !== 'provider') {
            return true;
        }

        if ($menuKey === 'roles_permissions') {
            return self::isProviderOwner($user);
        }

        if (self::isProviderOwner($user)) {
            return true;
        }

        $permissionKeys = self::userPermissionKeys($user);

        if ($menuKey === 'customers' && in_array('bookings', $permissionKeys, true)) {
            return true;
        }

        return in_array($menuKey, $permissionKeys, true);
    }

    public static function routeMenuKey(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        if (Str::startsWith($routeName, 'provider-branch.')) {
            $routeName = 'provider.' . Str::after($routeName, 'provider-branch.');
        }

        $patterns = [
            'provider.dashboard' => 'dashboard',
            'provider.profile' => 'profile',
            'provider.profile.*' => 'profile',
            'provider.services.*' => 'services',
            'provider.staffs.*' => 'staffs',
            'provider.branch.*' => 'branch',
            'provider.bookings.*' => 'bookings',
            'provider.calendar.*' => 'calendar',
            'provider.queue.*' => 'queue',
            'provider.walk-in.*' => 'walk_in',
            'provider.staff.skills' => 'staff_skills',
            'provider.staff.skills.*' => 'staff_skills',
            'provider.staff.schedules' => 'staff_schedules',
            'provider.staff.schedules.*' => 'staff_schedules',
            'provider.payments.*' => 'payments',
            'provider.customers.*' => 'customers',
            'provider.reviews.*' => 'reviews',
            'provider.chat.*' => 'chat',
            'provider.notifications.*' => 'notifications',
            'provider.tickets.*' => 'tickets',
            'provider.roles-permissions.*' => 'roles_permissions',
        ];

        foreach ($patterns as $pattern => $menuKey) {
            if (Str::is($pattern, $routeName)) {
                return $menuKey;
            }
        }

        return null;
    }

    public static function isProviderOwner(User $user): bool
    {
        $providerId = (int) ($user->provider_id ?: $user->id);

        return $user->role === 'provider'
            && $providerId === (int) $user->id
            && empty($user->provider_role_id);
    }

    public static function providerOwnerId(User $user): int
    {
        return (int) ($user->provider_id ?: $user->id);
    }

    public static function providerProfile(?User $user): ?ProviderProfile
    {
        if (! $user || $user->role !== 'provider') {
            return null;
        }

        return ProviderProfile::query()
            ->where('user_id', self::providerOwnerId($user))
            ->first();
    }

    public static function documentStatus(?User $user): string
    {
        return self::providerProfile($user)?->document_status ?: 'pending';
    }

    public static function hasVerifiedDocuments(?User $user): bool
    {
        return self::documentStatus($user) === 'verified';
    }

    private static function userPermissionKeys(User $user): array
    {
        $cacheKey = $user->getAuthIdentifier() . ':' . ($user->provider_role_id ?: 'none');

        if (array_key_exists($cacheKey, self::$permissionCache)) {
            return self::$permissionCache[$cacheKey];
        }

        if (! $user->provider_role_id) {
            return self::$permissionCache[$cacheKey] = [];
        }

        $providerId = self::providerOwnerId($user);

        $role = ProviderRole::query()
            ->with('menuPermissions:id,provider_role_id,menu_key')
            ->whereKey($user->provider_role_id)
            ->where('provider_id', $providerId)
            ->where('status', 'active')
            ->first();

        if (! $role) {
            return self::$permissionCache[$cacheKey] = [];
        }

        return self::$permissionCache[$cacheKey] = $role->menuPermissions
            ->pluck('menu_key')
            ->unique()
            ->values()
            ->all();
    }
}

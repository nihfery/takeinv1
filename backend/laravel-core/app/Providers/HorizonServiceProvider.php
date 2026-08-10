<?php

namespace App\Providers;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the authorization boundary for the Horizon dashboard.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static function (?User $user = null): bool {
            $admin = Auth::guard('admin')->user();

            return $admin instanceof User && $admin->role === 'admin';
        });
    }
}

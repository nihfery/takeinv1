<?php

namespace App\Providers;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Media\Application\Contracts\MediaStorage;
use App\Modules\Media\Infrastructure\Storage\LaravelFilesystemMediaStorage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MediaStorage::class, LaravelFilesystemMediaStorage::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Preserve Sanctum token rows written before User moved out of
        // App\Models. New tokens keep the same discriminator as existing rows.
        Relation::morphMap([
            'App\\Models\\User' => User::class,
        ]);

        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('login', static function (Request $request): array {
            $identity = mb_strtolower(trim((string) ($request->input('email') ?: $request->input('login_email'))));

            $limits = [
                Limit::perMinute(5)->by(self::rateLimitKey('login-identity', $identity, $request->ip())),
                Limit::perMinute(30)->by(self::rateLimitKey('login-ip', $request->ip())),
            ];

            if ($identity !== '') {
                $limits[] = Limit::perHour(8)->by(self::rateLimitKey('login-account', $identity));
            }

            return $limits;
        });

        RateLimiter::for('registration', static function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->input('email')));

            $limits = [
                Limit::perHour(5)->by(self::rateLimitKey('registration-identity', $email, $request->ip())),
                Limit::perHour(20)->by(self::rateLimitKey('registration-ip', $request->ip())),
            ];

            if ($email !== '') {
                $limits[] = Limit::perHour(5)->by(self::rateLimitKey('registration-account', $email));
            }

            return $limits;
        });

        RateLimiter::for('password-reset', static function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->input('email')));

            $limits = [
                Limit::perMinute(5)->by(self::rateLimitKey('password-reset', $email, $request->ip())),
                Limit::perHour(20)->by(self::rateLimitKey('password-reset-ip', $request->ip())),
            ];

            if ($email !== '') {
                $limits[] = Limit::perHour(5)->by(self::rateLimitKey('password-reset-account', $email));
            }

            return $limits;
        });

        RateLimiter::for('availability', static fn (Request $request): array => [
            Limit::perMinute(90)->by(self::actorKey($request, 'availability')),
            Limit::perMinute(240)->by(self::rateLimitKey('availability-ip', $request->ip())),
        ]);

        RateLimiter::for('search', static fn (Request $request): array => [
            Limit::perMinute(120)->by(self::actorKey($request, 'search')),
            Limit::perMinute(300)->by(self::rateLimitKey('search-ip', $request->ip())),
        ]);

        RateLimiter::for('booking-write', static fn (Request $request): array => [
            Limit::perMinute(20)->by(self::actorKey($request, 'booking-write')),
            Limit::perMinute(60)->by(self::rateLimitKey('booking-write-ip', $request->ip())),
        ]);

        RateLimiter::for('coupon-validation', static fn (Request $request): array => [
            Limit::perMinute(30)->by(self::actorKey($request, 'coupon-validation')),
            Limit::perMinute(90)->by(self::rateLimitKey('coupon-validation-ip', $request->ip())),
        ]);

        RateLimiter::for('payment-write', static fn (Request $request): array => [
            Limit::perMinute(12)->by(self::actorKey($request, 'payment-write')),
            Limit::perMinute(40)->by(self::rateLimitKey('payment-write-ip', $request->ip())),
        ]);

        RateLimiter::for('provider-write', static function (Request $request): array {
            $user = $request->user();
            $actorId = (string) ($user?->getAuthIdentifier() ?: 'guest');
            $providerId = (string) ($user?->provider_id ?: $user?->getAuthIdentifier() ?: 'guest');

            return [
                Limit::perMinute(90)->by(self::rateLimitKey('provider-actor', $actorId)),
                Limit::perMinute(300)->by(self::rateLimitKey('provider-owner', $providerId)),
            ];
        });

        RateLimiter::for('webhook', static fn (Request $request): array => [
            Limit::perMinute(300)->by(self::rateLimitKey('webhook-ip', $request->ip())),
        ]);
    }

    private static function actorKey(Request $request, string $scope): string
    {
        $actor = $request->user()?->getAuthIdentifier();

        return $actor
            ? self::rateLimitKey($scope, 'user', (string) $actor)
            : self::rateLimitKey($scope, 'ip', $request->ip());
    }

    private static function rateLimitKey(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }
}

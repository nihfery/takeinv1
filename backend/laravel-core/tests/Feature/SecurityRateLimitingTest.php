<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityRateLimitingTest extends TestCase
{
    public function test_required_named_rate_limiters_are_registered(): void
    {
        foreach ([
            'login',
            'registration',
            'password-reset',
            'availability',
            'search',
            'booking-write',
            'coupon-validation',
            'payment-write',
            'provider-write',
            'webhook',
        ] as $limiter) {
            $this->assertNotNull(RateLimiter::limiter($limiter), "Missing rate limiter [{$limiter}].");
        }
    }

    public function test_security_sensitive_routes_use_the_expected_limiters(): void
    {
        $expected = [
            'api.auth.login' => 'throttle:login',
            'api.auth.register-customer' => 'throttle:registration',
            'api.auth.register-provider' => 'throttle:registration',
            'api.branches.index' => 'throttle:search',
            'api.customer.booking.check-availability' => 'throttle:availability',
            'api.customer.bookings.store' => 'throttle:booking-write',
            'api.customer.bookings.finalize' => 'throttle:booking-write',
            'api.coupons.validate' => 'throttle:coupon-validation',
            'api.customer.bookings.payment.charge' => 'throttle:payment-write',
            'api.customer.bookings.payment.status' => 'throttle:payment-write',
            'api.provider.services.store' => 'throttle:provider-write',
            'api.provider.subscriptions.purchase' => 'throttle:provider-write',
            'api.midtrans.notification' => 'throttle:webhook',
            'provider.signin' => 'throttle:login',
            'admin.login.post' => 'throttle:login',
        ];

        foreach ($expected as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Missing route [{$routeName}].");
            $this->assertContains($middleware, $route->middleware(), "Route [{$routeName}] is not protected by [{$middleware}].");
        }
    }

    public function test_login_limiter_rejects_repeated_attempts_for_the_same_identity_and_ip(): void
    {
        Route::post('/_security-test/login-rate-limit', static fn () => response()->noContent())
            ->middleware('throttle:login');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/_security-test/login-rate-limit', [
                'email' => 'rate-limit@example.test',
            ])->assertNoContent();
        }

        $this->postJson('/_security-test/login-rate-limit', [
            'email' => 'rate-limit@example.test',
        ])->assertTooManyRequests();
    }

    public function test_login_limiter_throttles_one_identity_across_rotating_source_ips(): void
    {
        Route::post('/_security-test/login-account-rate-limit', static fn () => response()->noContent())
            ->middleware('throttle:login');

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $this
                ->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$attempt}"])
                ->postJson('/_security-test/login-account-rate-limit', [
                    'email' => 'rotating-ip@example.test',
                ])
                ->assertNoContent();
        }

        $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson('/_security-test/login-account-rate-limit', [
                'email' => 'rotating-ip@example.test',
            ])
            ->assertTooManyRequests();
    }

    public function test_forwarded_ip_is_ignored_when_the_direct_client_is_not_a_trusted_proxy(): void
    {
        Route::get('/_security-test/client-ip', static fn (Request $request) => response()->json([
            'ip' => $request->ip(),
        ]));

        $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('X-Forwarded-For', '198.51.100.20')
            ->getJson('/_security-test/client-ip')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.10');
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureProviderAccountActive;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_admin_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_provider_landing_page_redirects_to_frontend_app(): void
    {
        $response = $this->get('/provider');

        $response->assertRedirect(config('services.frontend.provider_url'));
    }

    public function test_customer_landing_page_redirects_to_frontend_app(): void
    {
        $response = $this->get('/customer');

        $response->assertRedirect(config('services.frontend.customer_url'));
    }

    public function test_provider_logout_redirects_to_provider_frontend_app(): void
    {
        $user = new User([
            'name' => 'Provider Test',
            'email' => 'provider@example.com',
            'role' => 'provider',
        ]);
        $user->id = 99;

        $response = $this
            ->withoutMiddleware(EnsureProviderAccountActive::class)
            ->actingAs($user, 'provider')
            ->post(route('provider.logout'));

        $response->assertRedirect(config('services.frontend.provider_url'));
        $this->assertGuest('provider');
    }

    public function test_admin_and_provider_guards_can_hold_separate_sessions(): void
    {
        $admin = new User([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
        $admin->id = 10;

        $provider = new User([
            'name' => 'Provider Test',
            'email' => 'provider@example.com',
            'role' => 'provider',
        ]);
        $provider->id = 11;

        $this->actingAs($admin, 'admin')
            ->actingAs($provider, 'provider');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($provider, 'provider');
    }

    public function test_provider_owner_and_branch_guards_can_hold_separate_sessions(): void
    {
        $owner = new User([
            'name' => 'Provider Owner',
            'email' => 'owner@example.com',
            'role' => 'provider',
        ]);
        $owner->id = 20;

        $branch = new User([
            'name' => 'Provider Branch',
            'email' => 'branch@example.com',
            'role' => 'provider',
            'provider_id' => 20,
            'branch_id' => 5,
            'provider_role_id' => 2,
        ]);
        $branch->id = 21;

        $this->actingAs($owner, 'provider')
            ->actingAs($branch, 'provider_branch');

        $this->assertAuthenticatedAs($owner, 'provider');
        $this->assertAuthenticatedAs($branch, 'provider_branch');
        $this->assertSame('provider-branch.dashboard', provider_route_name('provider.dashboard', true));
        $this->assertSame('/provider-branch/dashboard', provider_route('provider.dashboard', [], false, true));
    }

    public function test_provider_locked_routes_require_verified_documents(): void
    {
        $dashboardRoute = Route::getRoutes()->getByName('provider.dashboard');
        $chatRoute = Route::getRoutes()->getByName('provider.chat.index');
        $ticketsRoute = Route::getRoutes()->getByName('provider.tickets.index');

        $this->assertNotNull($dashboardRoute);
        $this->assertNotNull($chatRoute);
        $this->assertNotNull($ticketsRoute);

        $this->assertContains('provider.document.verified', $dashboardRoute->gatherMiddleware());

        $chatMiddleware = $chatRoute->gatherMiddleware();
        $this->assertContains('provider.menu:chat', $chatMiddleware);
        $this->assertContains('provider.document.verified', $chatMiddleware);

        $ticketsMiddleware = $ticketsRoute->gatherMiddleware();
        $this->assertContains('provider.menu:tickets', $ticketsMiddleware);
        $this->assertContains('provider.document.verified', $ticketsMiddleware);
    }

    public function test_provider_tickets_routes_use_tickets_menu_access(): void
    {
        $indexRoute = Route::getRoutes()->getByName('provider.tickets.index');
        $storeRoute = Route::getRoutes()->getByName('provider.tickets.store');

        $this->assertNotNull($indexRoute);
        $this->assertNotNull($storeRoute);
        $this->assertContains('provider.menu:tickets', $indexRoute->gatherMiddleware());
        $this->assertContains('provider.menu:tickets', $storeRoute->gatherMiddleware());
    }

    public function test_admin_ticket_review_routes_exist(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('admin.tickets.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('admin.tickets.approve'));
        $this->assertNotNull(Route::getRoutes()->getByName('admin.tickets.reject'));
        $this->assertNotNull(Route::getRoutes()->getByName('admin.chat.ticket.end'));
    }

    public function test_realtime_notification_routes_exist(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('notifications.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('notifications.read-all'));
        $this->assertNotNull(Route::getRoutes()->getByName('notifications.read'));
        $this->assertNotNull(Route::getRoutes()->getByName('provider.notifications.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('provider.notifications.read-all'));
        $this->assertNotNull(Route::getRoutes()->getByName('provider.notifications.read'));
        $this->assertNotNull(Route::getRoutes()->getByName('provider-branch.dashboard'));
        $this->assertNotNull(Route::getRoutes()->getByName('provider-branch.logout'));
        $this->assertNotNull(Route::getRoutes()->getByName('provider-branch.notifications.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('admin.notifications.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('admin.notifications.read-all'));
        $this->assertNotNull(Route::getRoutes()->getByName('admin.notifications.read'));
    }
}

<?php

namespace Tests\Feature\Security;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription;
use App\Modules\Subscription\Infrastructure\Persistence\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'provider-subscription-test-key',
            'services.midtrans.is_production' => false,
        ]);
        Http::preventStrayRequests();
    }

    public function test_subscription_purchase_requires_an_authenticated_provider_owner(): void
    {
        $plan = $this->subscriptionPlan();

        $this->postJson(route('api.provider.subscriptions.purchase', $plan))
            ->assertUnauthorized();

        foreach (['admin', 'customer'] as $role) {
            $actor = User::factory()->create(['role' => $role]);

            $this->actingAs($actor, 'sanctum')
                ->postJson(route('api.provider.subscriptions.purchase', $plan))
                ->assertForbidden();
        }

        $owner = $this->provider();
        $branch = $this->branch($owner, 'Subscription Branch');
        $branchAccount = $this->branchAccount($owner, $branch, []);

        $this->actingAs($branchAccount, 'sanctum')
            ->getJson(route('api.provider.subscriptions.index'))
            ->assertForbidden();

        $this->actingAs($branchAccount, 'sanctum')
            ->postJson(route('api.provider.subscriptions.purchase', $plan))
            ->assertForbidden();

        $this->assertDatabaseCount('provider_subscriptions', 0);
    }

    public function test_provider_owner_can_list_and_purchase_subscription_without_contract_changes(): void
    {
        $owner = $this->provider();
        $plan = $this->subscriptionPlan();
        $statusChecks = 0;
        Http::fake(function ($request) use (&$statusChecks) {
            if (str_ends_with($request->url(), '/status')) {
                $statusChecks++;
                $segments = explode('/', parse_url($request->url(), PHP_URL_PATH));
                $orderId = rawurldecode($segments[count($segments) - 2]);
                $isPending = $statusChecks === 1;

                return Http::response([
                    'status_code' => $isPending ? '201' : '407',
                    'status_message' => $isPending
                        ? 'Transaction is pending'
                        : 'Transaction is expired',
                    'transaction_id' => 'subscription-transaction-1',
                    'order_id' => $orderId,
                    'gross_amount' => '250000.00',
                    'currency' => 'IDR',
                    'transaction_status' => $isPending ? 'pending' : 'expire',
                    'fraud_status' => 'accept',
                ], 200);
            }

            if (str_ends_with($request->url(), '/expire')) {
                return Http::response([
                    'status_code' => '407',
                    'status_message' => 'Transaction is expired',
                    'transaction_status' => 'expire',
                ], 200);
            }

            $orderId = $request['transaction_details']['order_id'];

            return Http::response([
                'status_code' => '201',
                'status_message' => 'Success, Bank Transfer transaction is created',
                'transaction_id' => 'subscription-transaction-1',
                'order_id' => $orderId,
                'gross_amount' => '250000.00',
                'currency' => 'IDR',
                'payment_type' => 'bank_transfer',
                'transaction_status' => 'pending',
                'va_numbers' => [[
                    'bank' => 'bca',
                    'va_number' => '12345678901',
                ]],
                'expiry_time' => now()->addMinutes(7)->format('Y-m-d H:i:s O'),
            ], 201);
        });

        $this->actingAs($owner, 'sanctum')
            ->getJson(route('api.provider.subscriptions.index'))
            ->assertOk()
            ->assertJsonPath('plans.0.id', $plan->id)
            ->assertJsonPath('current_subscription', null);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson(route('api.provider.subscriptions.purchase', $plan), [
                'payment_channel' => 'bca_va',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Berhasil membuat tagihan subscription')
            ->assertJsonPath('subscription.provider_id', $owner->id)
            ->assertJsonPath('subscription.plan_id', $plan->id)
            ->assertJsonPath('subscription.payment_status', 'pending')
            ->assertJsonPath('payment.payment_channel', 'bca_va')
            ->assertJsonPath('payment.payment_code_label', 'BCA Virtual Account')
            ->assertJsonPath('payment.payment_code', '12345678901')
            ->assertJsonPath('payment.provider_status', 'pending');

        $this->assertStringStartsWith('SUB-', $response->json('order_id'));
        $this->assertLessThanOrEqual(50, strlen($response->json('order_id')));
        $this->assertArrayNotHasKey('gateway_response', $response->json('subscription'));
        $this->assertArrayNotHasKey('gateway_notification', $response->json('subscription'));
        $subscriptionId = $response->json('subscription.id');
        $this->assertDatabaseHas('provider_subscriptions', [
            'id' => $subscriptionId,
            'provider_id' => $owner->id,
            'plan_id' => $plan->id,
            'payment_status' => 'pending',
            'subscription_status' => 'inactive',
            'payment_channel' => 'bca_va',
            'midtrans_transaction_status' => 'pending',
            'payment_code' => '12345678901',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => (string) $owner->id,
            'action' => 'subscription.purchase.created',
            'resource_type' => 'App\\Modules\\Subscription\\Infrastructure\\Persistence\\Models\\ProviderSubscription',
            'resource_id' => (string) $subscriptionId,
            'provider_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => (string) $owner->id,
            'action' => 'subscription.gateway-charge.created',
            'resource_id' => (string) $subscriptionId,
            'provider_id' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson(route('api.provider.subscriptions.purchase', $plan), [
                'payment_channel' => 'bca_va',
            ])
            ->assertOk()
            ->assertJsonPath('subscription.id', $subscriptionId)
            ->assertJsonPath('order_id', $response->json('order_id'));

        $this->assertDatabaseCount('provider_subscriptions', 1);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
            && $request['payment_type'] === 'bank_transfer'
            && $request['bank_transfer']['bank'] === 'bca'
            && $request['transaction_details']['gross_amount'] === 250000);

        $this->travel(8)->minutes();
        $replacement = $this->actingAs($owner, 'sanctum')
            ->postJson(route('api.provider.subscriptions.purchase', $plan), [
                'payment_channel' => 'bca_va',
            ])
            ->assertOk();

        $this->assertNotSame($subscriptionId, $replacement->json('subscription.id'));
        $this->assertDatabaseHas('provider_subscriptions', [
            'id' => $subscriptionId,
            'payment_status' => 'expired',
            'subscription_status' => 'inactive',
        ]);
        $this->assertNotNull(
            ProviderSubscription::findOrFail($subscriptionId)->superseded_at
        );
        $this->assertDatabaseCount('provider_subscriptions', 2);
        Http::assertSentCount(5);
    }

    public function test_branch_account_needs_explicit_menu_permission_for_provider_api_writes(): void
    {
        $owner = $this->provider();
        $branch = $this->branch($owner, 'Permission Branch');
        $service = $this->service($owner, [$branch->id], 'Permission Service');
        $branchAccount = $this->branchAccount($owner, $branch, []);

        $this->actingAs($branchAccount, 'sanctum')
            ->patchJson(route('api.provider.services.toggle-status', $service))
            ->assertForbidden();

        $this->assertSame('active', $service->fresh()->status);
    }

    public function test_subscription_replacement_fails_closed_when_old_gateway_status_is_unavailable(): void
    {
        $owner = $this->provider();
        $plan = $this->subscriptionPlan();
        $subscription = ProviderSubscription::query()->create([
            'provider_id' => $owner->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'price' => $plan->price,
            'currency' => 'IDR',
            'duration_days' => $plan->duration_days,
            'max_branches' => $plan->max_branches,
            'payment_status' => 'pending',
            'subscription_status' => 'inactive',
            'midtrans_order_id' => 'SUB-' . Str::upper((string) Str::ulid()),
            'payment_channel' => 'qris',
            'midtrans_transaction_status' => 'pending',
            'gateway_expires_at' => now()->subMinute(),
            'gateway_response' => ['transaction_status' => 'pending'],
        ]);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'status_code' => '503',
                'status_message' => 'Gateway temporarily unavailable.',
            ], 503),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson(route('api.provider.subscriptions.purchase', $plan), [
                'payment_channel' => 'qris',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment');

        $subscription = $subscription->fresh();
        $this->assertSame('pending', $subscription->payment_status);
        $this->assertSame('inactive', $subscription->subscription_status);
        $this->assertNull($subscription->superseded_at);
        $this->assertDatabaseCount('provider_subscriptions', 1);
        Http::assertSentCount(1);
    }

    public function test_provider_must_be_active_and_verified_for_operational_api_writes(): void
    {
        $unverified = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create([
            'user_id' => $unverified->id,
            'status' => 'active',
            'document_status' => 'pending',
        ]);
        $unverifiedService = $this->service($unverified, [], 'Unverified Service');

        $this->actingAs($unverified, 'sanctum')
            ->patchJson(route('api.provider.services.toggle-status', $unverifiedService))
            ->assertForbidden();

        $inactive = User::factory()->create(['role' => 'provider']);
        ProviderProfile::create([
            'user_id' => $inactive->id,
            'status' => 'inactive',
            'document_status' => 'verified',
        ]);
        $inactiveService = $this->service($inactive, [], 'Inactive Service');

        $this->actingAs($inactive, 'sanctum')
            ->patchJson(route('api.provider.services.toggle-status', $inactiveService))
            ->assertForbidden();

        $this->assertSame('active', $unverifiedService->fresh()->status);
        $this->assertSame('active', $inactiveService->fresh()->status);
    }

    public function test_authorized_branch_account_keeps_access_to_its_own_service(): void
    {
        $owner = $this->provider();
        $branch = $this->branch($owner, 'Allowed Branch');
        $service = $this->service($owner, [$branch->id], 'Allowed Service');
        $branchAccount = $this->branchAccount($owner, $branch, ['services']);

        $this->actingAs($branchAccount, 'sanctum')
            ->patchJson(route('api.provider.services.toggle-status', $service))
            ->assertOk();

        $this->assertSame('inactive', $service->fresh()->status);
    }

    public function test_provider_cannot_mutate_another_providers_resources_by_id(): void
    {
        $resourceOwner = $this->provider();
        $attacker = $this->provider();
        $branch = $this->branch($resourceOwner, 'Foreign Provider Branch');
        $service = $this->service($resourceOwner, [$branch->id], 'Foreign Provider Service');
        $staff = $this->staff($resourceOwner, $branch, 'foreign-provider');

        $this->actingAs($attacker, 'sanctum')
            ->patchJson(route('api.provider.services.toggle-status', $service))
            ->assertForbidden();

        $this->actingAs($attacker, 'sanctum')
            ->deleteJson(route('api.provider.staff.destroy', $staff))
            ->assertForbidden();

        $this->actingAs($attacker, 'sanctum')
            ->deleteJson(route('api.provider.branches.destroy', $branch))
            ->assertForbidden();

        $this->assertSame('active', $service->fresh()->status);
        $this->assertDatabaseHas('provider_staffs', ['id' => $staff->id]);
        $this->assertDatabaseHas('provider_branches', ['id' => $branch->id]);
    }

    public function test_branch_account_cannot_mutate_sibling_branch_resources_by_id(): void
    {
        $owner = $this->provider();
        $ownBranch = $this->branch($owner, 'Actor Branch');
        $siblingBranch = $this->branch($owner, 'Sibling Branch');
        $service = $this->service($owner, [$siblingBranch->id], 'Sibling Service');
        $staff = $this->staff($owner, $siblingBranch, 'sibling-branch');
        $branchAccount = $this->branchAccount($owner, $ownBranch, ['services', 'staffs', 'branch']);

        $this->actingAs($branchAccount, 'sanctum')
            ->patchJson(route('api.provider.services.toggle-status', $service))
            ->assertForbidden();

        $this->actingAs($branchAccount, 'sanctum')
            ->deleteJson(route('api.provider.staff.destroy', $staff))
            ->assertForbidden();

        $this->actingAs($branchAccount, 'sanctum')
            ->deleteJson(route('api.provider.branches.destroy', $siblingBranch))
            ->assertForbidden();

        $this->assertSame('active', $service->fresh()->status);
        $this->assertDatabaseHas('provider_staffs', ['id' => $staff->id]);
        $this->assertDatabaseHas('provider_branches', ['id' => $siblingBranch->id]);
    }

    private function provider(): User
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        return $provider;
    }

    private function branch(User $provider, string $name): ProviderBranch
    {
        return ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => $name,
            'email' => str($name)->slug() . '-' . $provider->id . '@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Authorization Test',
            'country_id' => 'Indonesia',
            'state_id' => 'Jawa Barat',
            'city_id' => 'Bandung',
            'zip_code' => '40111',
            'working_start_hour' => '09:00',
            'working_end_hour' => '18:00',
            'working_days' => ['monday'],
            'holidays' => [],
            'status' => 'active',
        ]);
    }

    private function service(User $provider, array $branchIds, string $title): Service
    {
        return Service::create([
            'provider_id' => $provider->id,
            'title' => $title,
            'slug' => str($title)->slug() . '-' . $provider->id,
            'category' => 'Hair',
            'price' => 100000,
            'branch_ids' => $branchIds,
            'status' => 'active',
            'verify_status' => 'pending',
        ]);
    }

    private function staff(User $provider, ProviderBranch $branch, string $suffix): ProviderStaff
    {
        return ProviderStaff::create([
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'first_name' => 'Security',
            'last_name' => 'Tester',
            'email' => $suffix . '-' . $provider->id . '@example.test',
            'gender' => 'female',
            'role' => 'staff',
            'status' => 'active',
        ]);
    }

    private function branchAccount(User $owner, ProviderBranch $branch, array $permissions): User
    {
        $role = ProviderRole::create([
            'provider_id' => $owner->id,
            'branch_id' => $branch->id,
            'role_name' => 'API Branch ' . $branch->id,
            'slug' => 'api-branch-' . $branch->id,
            'status' => 'active',
        ]);

        $role->menuPermissions()->createMany(
            collect($permissions)
                ->map(fn (string $menuKey) => ['menu_key' => $menuKey])
                ->all()
        );

        return User::factory()->create([
            'role' => 'provider',
            'provider_id' => $owner->id,
            'branch_id' => $branch->id,
            'provider_role_id' => $role->id,
        ]);
    }

    private function subscriptionPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Security Plan',
            'description' => 'Authorization regression fixture.',
            'price' => 250000,
            'duration_days' => 30,
            'max_branches' => 3,
            'is_active' => true,
        ]);
    }
}

<?php

namespace Tests\Feature\Security;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatThread;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
use Tests\TestCase;

class ReverbChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'reverb-channel-test-key',
            'broadcasting.connections.reverb.secret' => 'reverb-channel-test-secret',
            'broadcasting.connections.reverb.app_id' => 'reverb-channel-test-app',
        ]);

        $manager = app(BroadcastManager::class);
        $manager->setDefaultDriver('reverb');
        $manager->purge('reverb');

        // The application booted its channel definitions before this test
        // selected an isolated Reverb driver. Register the same production
        // definitions there so /broadcasting/auth is exercised end to end.
        require base_path('routes/channels.php');

        $this->resetProviderMenuPermissionCache();
    }

    protected function tearDown(): void
    {
        $this->resetProviderMenuPermissionCache();

        parent::tearDown();
    }

    public function test_customer_can_only_authorize_their_own_user_channels(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer, 'web');

        $this->authorizeChannel("private-App.Models.User.{$customer->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
        $this->authorizeChannel("private-notifications.user.{$customer->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->authorizeChannel("private-App.Models.User.{$otherCustomer->id}")
            ->assertForbidden();
        $this->authorizeChannel("private-notifications.user.{$otherCustomer->id}")
            ->assertForbidden();
    }

    public function test_active_verified_provider_owner_can_authorize_owned_open_chat(): void
    {
        $owner = $this->provider('active', 'verified');
        $thread = $this->thread($owner, [
            'conversation_type' => 'provider_admin',
            'provider_user_id' => $owner->id,
        ]);

        $this->actingAs($owner, 'provider');

        $this->authorizeChannel("private-chat.thread.{$thread->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_provider_branch_participant_requires_tenant_scope_and_chat_permission(): void
    {
        $owner = $this->provider('active', 'verified');
        $allowedBranchAccount = $this->branchAccount($owner, true, 'allowed');
        $deniedBranchAccount = $this->branchAccount($owner, false, 'denied');
        $nonParticipantBranchAccount = $this->branchAccount($owner, true, 'non-participant');
        $thread = $this->thread($owner, [
            'conversation_type' => 'provider_branch',
            'provider_user_id' => $owner->id,
            'branch_user_id' => $allowedBranchAccount->id,
        ]);
        $noPermissionThread = $this->thread($owner, [
            'conversation_type' => 'provider_branch',
            'provider_user_id' => $owner->id,
            'branch_user_id' => $deniedBranchAccount->id,
        ]);

        $this->actingAs($allowedBranchAccount, 'provider_branch');
        $this->authorizeChannel("private-chat.thread.{$thread->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($deniedBranchAccount, 'provider_branch');
        $this->authorizeChannel("private-chat.thread.{$noPermissionThread->id}")
            ->assertForbidden();

        $this->actingAs($nonParticipantBranchAccount, 'provider_branch');
        $this->authorizeChannel("private-chat.thread.{$thread->id}")
            ->assertForbidden();
    }

    public function test_admin_can_authorize_provider_admin_chat_but_not_provider_branch_chat(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = $this->provider('active', 'verified');
        $branchAccount = $this->branchAccount($owner, true, 'admin-check');
        $providerAdminThread = $this->thread($owner, [
            'conversation_type' => 'provider_admin',
            'provider_user_id' => $owner->id,
        ]);
        $providerBranchThread = $this->thread($owner, [
            'conversation_type' => 'provider_branch',
            'provider_user_id' => $owner->id,
            'branch_user_id' => $branchAccount->id,
        ]);

        $this->actingAs($admin, 'admin');

        $this->authorizeChannel("private-chat.thread.{$providerAdminThread->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
        $this->authorizeChannel("private-chat.thread.{$providerBranchThread->id}")
            ->assertForbidden();
    }

    public function test_cross_tenant_provider_cannot_authorize_another_providers_chat(): void
    {
        $owner = $this->provider('active', 'verified');
        $otherOwner = $this->provider('active', 'verified');
        $thread = $this->thread($owner, [
            'conversation_type' => 'provider_admin',
            'provider_user_id' => $owner->id,
        ]);

        $this->actingAs($otherOwner, 'provider');

        $this->authorizeChannel("private-chat.thread.{$thread->id}")
            ->assertForbidden();
    }

    public function test_inactive_or_unverified_provider_cannot_bypass_chat_page_middleware(): void
    {
        $inactiveOwner = $this->provider('inactive', 'verified');
        $inactiveThread = $this->thread($inactiveOwner, [
            'conversation_type' => 'provider_admin',
            'provider_user_id' => $inactiveOwner->id,
        ]);

        $this->actingAs($inactiveOwner, 'provider');
        $this->authorizeChannel("private-chat.thread.{$inactiveThread->id}")
            ->assertForbidden();

        $unverifiedOwner = $this->provider('active', 'pending');
        $unverifiedThread = $this->thread($unverifiedOwner, [
            'conversation_type' => 'provider_admin',
            'provider_user_id' => $unverifiedOwner->id,
        ]);

        $this->actingAs($unverifiedOwner, 'provider');
        $this->authorizeChannel("private-chat.thread.{$unverifiedThread->id}")
            ->assertForbidden();
    }

    public function test_closed_or_unapproved_chat_cannot_be_authorized(): void
    {
        $owner = $this->provider('active', 'verified');
        $pendingThread = $this->thread($owner, [
            'conversation_type' => 'provider_admin',
            'provider_user_id' => $owner->id,
            'ticket_status' => 'pending',
        ]);
        $closedThread = $this->thread($owner, [
            'conversation_type' => 'provider_admin',
            'provider_user_id' => $owner->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        $branchAccount = $this->branchAccount($owner, true, 'closed-thread');
        $closedBranchThread = $this->thread($owner, [
            'conversation_type' => 'provider_branch',
            'provider_user_id' => $owner->id,
            'branch_user_id' => $branchAccount->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->actingAs($owner, 'provider');

        $this->authorizeChannel("private-chat.thread.{$pendingThread->id}")
            ->assertForbidden();
        $this->authorizeChannel("private-chat.thread.{$closedThread->id}")
            ->assertForbidden();

        $this->actingAs($branchAccount, 'provider_branch');
        $this->authorizeChannel("private-chat.thread.{$closedBranchThread->id}")
            ->assertForbidden();
    }

    public function test_customer_and_guest_cannot_authorize_chat_threads(): void
    {
        $owner = $this->provider('active', 'verified');
        $thread = $this->thread($owner, [
            'conversation_type' => 'provider_admin',
            'provider_user_id' => $owner->id,
        ]);

        $this->authorizeChannel("private-chat.thread.{$thread->id}")
            ->assertForbidden();

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer, 'web');

        $this->authorizeChannel("private-chat.thread.{$thread->id}")
            ->assertForbidden();
    }

    private function authorizeChannel(string $channel)
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);
    }

    private function provider(string $status, string $documentStatus): User
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => $status,
            'document_status' => $documentStatus,
        ]);

        return $provider;
    }

    private function branchAccount(User $owner, bool $canChat, string $suffix): User
    {
        $branch = ProviderBranch::query()->create([
            'provider_id' => $owner->id,
            'branch_name' => "Branch {$suffix}",
            'status' => 'active',
        ]);
        $role = ProviderRole::query()->create([
            'provider_id' => $owner->id,
            'branch_id' => $branch->id,
            'role_name' => "Branch role {$suffix}",
            'slug' => "branch-role-{$suffix}",
            'status' => 'active',
        ]);

        if ($canChat) {
            $role->menuPermissions()->create(['menu_key' => 'chat']);
        }

        return User::factory()->create([
            'role' => 'provider',
            'provider_id' => $owner->id,
            'branch_id' => $branch->id,
            'provider_role_id' => $role->id,
        ]);
    }

    private function thread(User $owner, array $attributes): ChatThread
    {
        return ChatThread::query()->create(array_merge([
            'provider_id' => $owner->id,
            'conversation_type' => 'provider_admin',
            'provider_user_id' => $owner->id,
            'status' => 'open',
            'ticket_status' => 'approved',
        ], $attributes));
    }

    private function resetProviderMenuPermissionCache(): void
    {
        $property = new ReflectionProperty(ProviderMenuAccess::class, 'permissionCache');
        $property->setValue(null, []);
    }
}

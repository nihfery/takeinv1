<?php

namespace Tests\Feature\Security;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SensitiveActionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_role_permission_status_change_is_audited_without_credentials(): void
    {
        $provider = $this->verifiedProvider();
        $branch = $this->branch($provider);
        $role = ProviderRole::query()->create([
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'role_name' => 'Branch operator',
            'slug' => 'branch-operator',
            'status' => 'active',
        ]);
        $role->menuPermissions()->create(['menu_key' => 'bookings']);
        User::factory()->create([
            'role' => 'provider',
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'provider_role_id' => $role->id,
            'email' => 'branch-secret@example.test',
            'password' => Hash::make('never-audit-this-password'),
        ]);

        $this->actingAs($provider, 'provider')
            ->patch(route('provider.roles-permissions.toggle-status', $role))
            ->assertSessionHas('success');

        $audit = AuditLog::query()
            ->where('action', 'provider.role-permissions.status-updated')
            ->where('resource_id', (string) $role->id)
            ->firstOrFail();

        $this->assertSame('active', $audit->before['status']);
        $this->assertSame('inactive', $audit->after['status']);
        $this->assertSame(['bookings'], $audit->after['menu_keys']);
        $this->assertSame((int) $provider->id, (int) $audit->provider_id);
        $this->assertStringNotContainsString('branch-secret@example.test', $audit->toJson());
        $this->assertStringNotContainsString('never-audit-this-password', $audit->toJson());
    }

    public function test_admin_and_provider_booking_status_mutations_are_audited(): void
    {
        $provider = $this->verifiedProvider();
        $branch = $this->branch($provider);
        $admin = User::factory()->create(['role' => 'admin']);
        $adminBooking = $this->booking($provider, $branch, 'AUDIT-ADMIN-001');
        $providerBooking = $this->booking($provider, $branch, 'AUDIT-PROVIDER-001');

        $this->actingAs($admin, 'sanctum')
            ->patchJson(route('api.admin.bookings.status', $adminBooking), [
                'status' => 'rescheduled',
            ])
            ->assertOk();

        $adminAudit = AuditLog::query()
            ->where('action', 'admin.booking.status-updated')
            ->where('resource_id', (string) $adminBooking->id)
            ->firstOrFail();

        $this->assertSame(['status' => 'open'], $adminAudit->before);
        $this->assertSame(['status' => 'rescheduled'], $adminAudit->after);
        $this->assertSame((string) $admin->id, $adminAudit->actor_id);

        $this->actingAs($provider, 'provider')
            ->post(route('provider.bookings.cancel', $providerBooking))
            ->assertSessionHas('success');

        $providerAudit = AuditLog::query()
            ->where('action', 'provider.booking.cancelled')
            ->where('resource_id', (string) $providerBooking->id)
            ->firstOrFail();

        $this->assertSame(['status' => 'open'], $providerAudit->before);
        $this->assertSame(['status' => 'cancelled'], $providerAudit->after);
        $this->assertSame((string) $provider->id, $providerAudit->actor_id);
        $this->assertSame((int) $branch->id, (int) $providerAudit->branch_id);
    }

    public function test_admin_password_change_is_audited_without_password_material(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.profile.password.update'), [
                'current_password' => 'current-password',
                'password' => 'replacement-password',
                'password_confirmation' => 'replacement-password',
            ])
            ->assertSessionHas('success');

        $audit = AuditLog::query()
            ->where('action', 'admin.security.password-changed')
            ->where('resource_id', (string) $admin->id)
            ->firstOrFail();

        $this->assertSame(['password_changed' => true], $audit->after);
        $this->assertNull($audit->before);
        $this->assertStringNotContainsString('current-password', $audit->toJson());
        $this->assertStringNotContainsString('replacement-password', $audit->toJson());
    }

    public function test_admin_account_identifier_change_is_audited_as_fingerprints_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-before@example.test',
            'username' => 'admin-before',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.profile.update'), [
                'name' => 'Audit Administrator',
                'username' => 'admin-after',
                'email' => 'admin-after@example.test',
                'phone_number' => '081234567890',
                'position' => 'Administrator',
                'bio' => 'Safe audit test.',
            ])
            ->assertSessionHas('success');

        $audit = AuditLog::query()
            ->where('action', 'admin.security.account-identifiers-updated')
            ->where('resource_id', (string) $admin->id)
            ->firstOrFail();

        $this->assertNotSame($audit->before, $audit->after);
        $this->assertSame(64, strlen($audit->before['email_fingerprint']));
        $this->assertSame(64, strlen($audit->after['email_fingerprint']));
        $this->assertStringNotContainsString('admin-before@example.test', $audit->toJson());
        $this->assertStringNotContainsString('admin-after@example.test', $audit->toJson());
        $this->assertStringNotContainsString('admin-before', $audit->toJson());
        $this->assertStringNotContainsString('admin-after', $audit->toJson());
    }

    public function test_provider_account_identifier_change_is_audited_as_fingerprints_only(): void
    {
        $provider = $this->verifiedProvider();
        $provider->update([
            'email' => 'provider-before@example.test',
            'username' => 'provider-before',
        ]);

        $this->actingAs($provider, 'provider')
            ->put(route('provider.profile.update'), [
                'name' => 'Audit Provider',
                'username' => 'provider-after',
                'email' => 'provider-after@example.test',
                'phone_number' => '081234567890',
            ])
            ->assertSessionHas('success');

        $audit = AuditLog::query()
            ->where('action', 'provider.security.account-identifiers-updated')
            ->where('resource_id', (string) $provider->id)
            ->firstOrFail();

        $this->assertNotSame($audit->before, $audit->after);
        $this->assertSame((int) $provider->id, (int) $audit->provider_id);
        $this->assertStringNotContainsString('provider-before@example.test', $audit->toJson());
        $this->assertStringNotContainsString('provider-after@example.test', $audit->toJson());
        $this->assertStringNotContainsString('provider-before', $audit->toJson());
        $this->assertStringNotContainsString('provider-after', $audit->toJson());
    }

    public function test_provider_api_account_identifier_change_uses_the_same_redacted_audit_contract(): void
    {
        $provider = $this->verifiedProvider();
        $provider->update([
            'email' => 'provider-api-before@example.test',
            'username' => 'provider-api-before',
        ]);

        $this->actingAs($provider, 'sanctum')
            ->putJson(route('api.provider.profile.update'), [
                'name' => 'API Audit Provider',
                'username' => 'provider-api-after',
                'email' => 'provider-api-after@example.test',
                'phone_number' => '081234567890',
            ])
            ->assertOk();

        $audit = AuditLog::query()
            ->where('action', 'provider.security.account-identifiers-updated')
            ->where('resource_id', (string) $provider->id)
            ->firstOrFail();

        $this->assertNotSame($audit->before, $audit->after);
        $this->assertSame((int) $provider->id, (int) $audit->provider_id);
        $this->assertStringNotContainsString('provider-api-before@example.test', $audit->toJson());
        $this->assertStringNotContainsString('provider-api-after@example.test', $audit->toJson());
    }

    public function test_branch_account_password_and_identifier_change_is_explicit_and_redacted(): void
    {
        $provider = $this->verifiedProvider();
        $branch = $this->branch($provider);
        $role = ProviderRole::query()->create([
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'role_name' => 'Branch operator',
            'slug' => 'branch-operator-update',
            'status' => 'active',
        ]);
        $role->menuPermissions()->create(['menu_key' => 'bookings']);
        User::factory()->create([
            'name' => 'Branch Account',
            'role' => 'provider',
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'provider_role_id' => $role->id,
            'email' => 'branch-before@example.test',
            'username' => 'branch-before',
            'password' => Hash::make('branch-old-password'),
        ]);

        $this->actingAs($provider, 'provider')
            ->put(route('provider.roles-permissions.update', $role), [
                'role_name' => 'Branch operator',
                'account_name' => 'Branch Account',
                'account_email' => 'branch-after@example.test',
                'account_password' => 'branch-new-password',
                'branch_id' => $branch->id,
                'description' => 'Audited branch account.',
                'status' => 'active',
                'menu_keys' => ['bookings'],
            ])
            ->assertSessionHas('success');

        $audit = AuditLog::query()
            ->where('action', 'provider.role-permissions.updated')
            ->where('resource_id', (string) $role->id)
            ->firstOrFail();

        $this->assertFalse($audit->before['password_changed']);
        $this->assertTrue($audit->after['password_changed']);
        $this->assertNotSame(
            $audit->before['account_email_fingerprints'],
            $audit->after['account_email_fingerprints'],
        );
        $this->assertStringNotContainsString('branch-before@example.test', $audit->toJson());
        $this->assertStringNotContainsString('branch-after@example.test', $audit->toJson());
        $this->assertStringNotContainsString('branch-old-password', $audit->toJson());
        $this->assertStringNotContainsString('branch-new-password', $audit->toJson());
    }

    private function verifiedProvider(): User
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        return $provider;
    }

    private function branch(User $provider): ProviderBranch
    {
        return ProviderBranch::query()->create([
            'provider_id' => $provider->id,
            'branch_name' => 'Audit Branch',
            'email' => 'audit-branch@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Audit No. 1',
            'country_id' => 'Indonesia',
            'state_id' => 'Jawa Barat',
            'city_id' => 'Bandung',
            'zip_code' => '40111',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'holidays' => [],
            'status' => 'active',
        ]);
    }

    private function booking(User $provider, ProviderBranch $branch, string $code): Booking
    {
        return Booking::query()->create([
            'booking_code' => $code,
            'booking_date' => now()->toDateString(),
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'booking_type' => 'walk_in',
            'total_price' => 100000,
            'total_duration' => 60,
            'participant_count' => 1,
            'customer_name' => 'Audit Customer',
            'status' => 'open',
        ]);
    }
}

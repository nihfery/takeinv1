<?php

namespace Tests\Feature\Security;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminProviderLifecycleAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_admin_approval_and_rejection_are_audited_without_document_paths(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $provider = $this->provider('submitted');
        $profile = $provider->providerProfile()->firstOrFail();
        $profile->update([
            'ktp_image' => 'provider/documents/ktp.jpg',
            'nib_number' => '1234567890123',
            'nib_document' => 'provider/documents/nib.pdf',
            'business_image' => 'provider/documents/business.jpg',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.providers.document-status', $provider), [
                'document_status' => 'verified',
            ])
            ->assertSessionHas('success');

        $approval = AuditLog::query()
            ->where('action', 'admin.provider.approved')
            ->where('resource_id', (string) $provider->id)
            ->firstOrFail();

        $this->assertSame(User::class, $approval->resource_type);
        $this->assertSame((string) $admin->id, $approval->actor_id);
        $this->assertSame((int) $provider->id, (int) $approval->provider_id);
        $this->assertSame([
            'document_status' => 'submitted',
            'status' => 'inactive',
        ], Arr::sortRecursive($approval->before));
        $this->assertSame([
            'document_status' => 'verified',
            'status' => 'active',
        ], Arr::sortRecursive($approval->after));
        $this->assertStringNotContainsString('provider/documents', $approval->toJson());
        $this->assertStringNotContainsString('1234567890123', $approval->toJson());

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.providers.document-status', $provider), [
                'document_status' => 'rejected',
                'document_note' => 'Needs a clearer scan.',
            ])
            ->assertSessionHas('success');

        $rejection = AuditLog::query()
            ->where('action', 'admin.provider.rejected')
            ->where('resource_id', (string) $provider->id)
            ->firstOrFail();

        $this->assertSame('verified', $rejection->before['document_status']);
        $this->assertSame('rejected', $rejection->after['document_status']);
        $this->assertStringNotContainsString('Needs a clearer scan.', $rejection->toJson());
    }

    public function test_api_admin_status_activation_and_suspension_are_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $provider = $this->provider('verified', 'active');

        $this->actingAs($admin, 'sanctum')
            ->patchJson(route('api.admin.providers.toggle-status', $provider))
            ->assertOk();

        $suspension = AuditLog::query()
            ->where('action', 'admin.provider.suspended')
            ->where('resource_id', (string) $provider->id)
            ->firstOrFail();

        $this->assertSame(['status' => 'active'], $suspension->before);
        $this->assertSame(['status' => 'inactive'], $suspension->after);
        $this->assertSame((string) $admin->id, $suspension->actor_id);

        $this->actingAs($admin, 'sanctum')
            ->patchJson(route('api.admin.providers.toggle-status', $provider))
            ->assertOk();

        $activation = AuditLog::query()
            ->where('action', 'admin.provider.activated')
            ->where('resource_id', (string) $provider->id)
            ->firstOrFail();

        $this->assertSame(['status' => 'inactive'], $activation->before);
        $this->assertSame(['status' => 'active'], $activation->after);
    }

    public function test_api_admin_provider_deletion_is_audited_after_the_mutation(): void
    {
        Storage::fake('public');
        Storage::fake('provider_documents');

        $admin = User::factory()->create(['role' => 'admin']);
        $provider = $this->provider('rejected');
        $providerId = (int) $provider->id;

        $this->actingAs($admin, 'sanctum')
            ->deleteJson(route('api.admin.providers.destroy', $provider))
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $providerId]);

        $deletion = AuditLog::query()
            ->where('action', 'admin.provider.deleted')
            ->where('resource_id', (string) $providerId)
            ->firstOrFail();

        $this->assertSame([
            'document_status' => 'rejected',
            'status' => 'inactive',
        ], Arr::sortRecursive($deletion->before));
        $this->assertSame(['deleted' => true], $deletion->after);
        $this->assertSame($providerId, (int) $deletion->provider_id);
    }

    public function test_admin_api_exposes_only_signed_authorized_document_urls(): void
    {
        Storage::fake('provider_documents');

        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $provider = $this->provider('submitted');
        $profile = $provider->providerProfile()->firstOrFail();
        $profile->update([
            'ktp_image' => 'providers/'.$provider->id.'/ktp/document.jpg',
            'nib_document' => 'providers/'.$provider->id.'/nib/document.pdf',
        ]);
        Storage::disk('provider_documents')->put($profile->ktp_image, 'private-ktp');
        Storage::disk('provider_documents')->put($profile->nib_document, 'private-nib');

        $payload = $this->actingAs($admin, 'sanctum')
            ->getJson(route('api.admin.providers.show', $provider))
            ->assertOk()
            ->assertJsonPath('data.provider_profile.has_ktp_document', true)
            ->assertJsonPath('data.provider_profile.has_nib_document', true)
            ->assertJsonMissingPath('data.provider_profile.ktp_image')
            ->assertJsonMissingPath('data.provider_profile.nib_document')
            ->json();

        $ktpUrl = $payload['document_access']['ktp_url'];
        $this->assertNotEmpty($ktpUrl);

        $documentResponse = $this->actingAs($admin, 'sanctum')
            ->get($ktpUrl)
            ->assertOk();

        $cacheControlDirectives = array_map(
            'trim',
            explode(',', strtolower((string) $documentResponse->headers->get('Cache-Control'))),
        );

        $this->assertContains('private', $cacheControlDirectives);
        $this->assertContains('no-store', $cacheControlDirectives);
        $this->assertContains('max-age=0', $cacheControlDirectives);

        $this->actingAs($customer, 'sanctum')
            ->get($ktpUrl)
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->getJson($ktpUrl)->assertUnauthorized();

        $this->travel(6)->minutes();
        $this->actingAs($admin, 'sanctum')
            ->get($ktpUrl)
            ->assertForbidden();
        $this->travelBack();

        $otherProvider = $this->provider('submitted');
        $tamperedUrl = str_replace(
            '/providers/'.$provider->id.'/documents/',
            '/providers/'.$otherProvider->id.'/documents/',
            $ktpUrl,
        );

        $this->actingAs($admin, 'sanctum')
            ->get($tamperedUrl)
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'provider.document.accessed',
            'resource_id' => (string) $profile->id,
        ]);
    }

    public function test_admin_api_rejects_branch_accounts_as_provider_lifecycle_targets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = $this->provider('verified', 'active');
        $branchAccount = User::factory()->create([
            'role' => 'provider',
            'provider_id' => $owner->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('api.admin.providers.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $branchAccount->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('api.admin.providers.show', $branchAccount))
            ->assertNotFound();

        $this->actingAs($admin, 'sanctum')
            ->patchJson(route('api.admin.providers.toggle-status', $branchAccount))
            ->assertNotFound();

        $this->actingAs($admin, 'sanctum')
            ->patchJson(route('api.admin.providers.document-status', $branchAccount), [
                'document_status' => 'rejected',
                'document_note' => 'Not an owner account.',
            ])
            ->assertNotFound();

        $signedDocumentUrl = URL::temporarySignedRoute(
            'api.admin.providers.documents.show',
            now()->addMinutes(5),
            ['provider' => $branchAccount->id, 'document' => 'ktp'],
        );

        $this->actingAs($admin, 'sanctum')
            ->get($signedDocumentUrl)
            ->assertNotFound();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson(route('api.admin.providers.destroy', $branchAccount))
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $branchAccount->id]);
        $this->assertDatabaseMissing('provider_profiles', ['user_id' => $branchAccount->id]);
    }

    private function provider(string $documentStatus, string $status = 'inactive'): User
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => $status,
            'document_status' => $documentStatus,
        ]);

        return $provider->refresh();
    }
}

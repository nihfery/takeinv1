<?php

namespace Tests\Feature\Security;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Services\ProviderDocumentStorage;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProviderDocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('provider_documents');
        Storage::fake('public');
    }

    public function test_new_ktp_and_nib_uploads_are_private_while_business_image_stays_public(): void
    {
        $owner = $this->provider();
        $pixel = $this->pixel();

        $this->actingAs($owner, 'provider')
            ->post(route('provider.profile.documents.update'), [
                'nib_number' => '1234567890123',
                'ktp_image' => UploadedFile::fake()->createWithContent('ktp.png', $pixel),
                'nib_document' => UploadedFile::fake()->createWithContent('nib.pdf', '%PDF-1.4 private test'),
                'business_image' => UploadedFile::fake()->createWithContent('business.png', $pixel),
            ])
            ->assertRedirect(route('provider.verification'));

        $profile = $owner->providerProfile()->firstOrFail();

        Storage::disk('provider_documents')->assertExists($profile->ktp_image);
        Storage::disk('provider_documents')->assertExists($profile->nib_document);
        Storage::disk('public')->assertMissing($profile->ktp_image);
        Storage::disk('public')->assertMissing($profile->nib_document);
        Storage::disk('public')->assertExists($profile->business_image);
        $this->assertStringStartsWith("providers/{$owner->id}/ktp/", $profile->ktp_image);
        $this->assertStringStartsWith("providers/{$owner->id}/nib/", $profile->nib_document);
    }

    public function test_owner_and_admin_can_read_documents_only_through_signed_authorized_actions(): void
    {
        [$owner, $profile] = $this->providerWithDocuments();
        $documents = app(ProviderDocumentStorage::class);
        $ownerUrls = $documents->temporaryProviderUrls($profile);
        $adminUrls = $documents->temporaryAdminUrls($profile);

        $ownerResponse = $this->actingAs($owner, 'provider')
            ->get($ownerUrls[ProviderDocumentStorage::KTP])
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString(
            'no-store',
            (string) $ownerResponse->headers->get('Cache-Control'),
        );

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get($adminUrls[ProviderDocumentStorage::NIB])
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => (string) $owner->id,
            'action' => 'provider.document.accessed',
            'resource_id' => (string) $profile->id,
            'provider_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => (string) $admin->id,
            'action' => 'provider.document.accessed',
            'resource_id' => (string) $profile->id,
            'provider_id' => $owner->id,
        ]);
    }

    public function test_sanctum_owner_can_read_a_signed_api_document_but_branch_account_is_forbidden(): void
    {
        [$owner, $profile] = $this->providerWithDocuments();
        $profileResponse = $this->actingAs($owner, 'sanctum')
            ->getJson(route('api.provider.profile.show'))
            ->assertOk();
        $url = $profileResponse->json('document_access.ktp_url');

        $this->assertIsString($url);
        $this->assertStringContainsString('/api/provider/profile/documents/ktp', $url);

        $this->actingAs($owner, 'sanctum')
            ->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => (string) $owner->id,
            'action' => 'provider.document.accessed',
            'resource_id' => (string) $profile->id,
            'provider_id' => $owner->id,
        ]);

        $branch = $this->branch($owner);
        $branchAccount = $this->branchAccount($owner, $branch, ['profile']);

        $this->actingAs($branchAccount, 'sanctum')
            ->get($url)
            ->assertForbidden();
    }

    public function test_unsigned_expired_branch_and_foreign_provider_requests_cannot_read_owner_documents(): void
    {
        [$owner, $profile] = $this->providerWithDocuments();
        $documents = app(ProviderDocumentStorage::class);
        $signedUrl = $documents->temporaryProviderUrls($profile)[ProviderDocumentStorage::KTP];

        $this->actingAs($owner, 'provider')
            ->get(route('provider.documents.show', ['document' => ProviderDocumentStorage::KTP]))
            ->assertForbidden();

        $this->travel(6)->minutes();
        $this->actingAs($owner, 'provider')->get($signedUrl)->assertForbidden();
        $this->travelBack();

        $branchAccount = User::factory()->create([
            'role' => 'provider',
            'provider_id' => $owner->id,
        ]);
        auth('provider')->logout();

        $this->actingAs($branchAccount, 'provider_branch')
            ->get($signedUrl)
            ->assertRedirect(route('provider.login'));

        $foreignOwner = $this->provider();
        auth('provider_branch')->logout();

        $this->actingAs($foreignOwner, 'provider')
            ->get($signedUrl)
            ->assertNotFound();
    }

    public function test_branch_profile_api_never_exposes_raw_sensitive_paths_or_signed_urls(): void
    {
        [$owner] = $this->providerWithDocuments();
        $branch = $this->branch($owner);
        $branchAccount = $this->branchAccount($owner, $branch, ['profile']);

        $response = $this->actingAs($branchAccount, 'sanctum')
            ->getJson(route('api.provider.profile.show'))
            ->assertOk()
            ->assertJsonPath('document_access.ktp_url', null)
            ->assertJsonPath('document_access.nib_url', null)
            ->assertJsonPath('document_access.has_ktp_document', true)
            ->assertJsonPath('document_access.has_nib_document', true);

        $profilePayload = $response->json('profile');

        $this->assertArrayNotHasKey('ktp_image', $profilePayload);
        $this->assertArrayNotHasKey('nib_document', $profilePayload);
    }

    public function test_legacy_public_documents_are_served_by_the_secure_action_without_deleting_them(): void
    {
        $owner = $this->provider();
        $profile = $owner->providerProfile()->firstOrFail();
        $profile->update([
            'ktp_image' => 'provider/documents/legacy-ktp.jpg',
            'nib_document' => 'provider/documents/legacy-nib.pdf',
        ]);

        Storage::disk('public')->put($profile->ktp_image, 'legacy ktp');
        Storage::disk('public')->put($profile->nib_document, 'legacy nib');

        $url = app(ProviderDocumentStorage::class)
            ->temporaryProviderUrls($profile->refresh())[ProviderDocumentStorage::KTP];

        $this->actingAs($owner, 'provider')->get($url)->assertOk();

        Storage::disk('public')->assertExists('provider/documents/legacy-ktp.jpg');
        Storage::disk('public')->assertExists('provider/documents/legacy-nib.pdf');
    }

    public function test_replacing_legacy_sensitive_documents_keeps_the_legacy_fallback_but_writes_new_files_privately(): void
    {
        $owner = $this->provider();
        $profile = $owner->providerProfile()->firstOrFail();
        $profile->update([
            'ktp_image' => 'provider/documents/legacy-ktp.jpg',
            'nib_number' => '1234567890123',
            'nib_document' => 'provider/documents/legacy-nib.pdf',
            'business_image' => 'provider/documents/legacy-business.jpg',
        ]);

        Storage::disk('public')->put($profile->ktp_image, 'legacy ktp');
        Storage::disk('public')->put($profile->nib_document, 'legacy nib');
        Storage::disk('public')->put($profile->business_image, 'legacy business');

        $this->actingAs($owner, 'provider')
            ->post(route('provider.profile.documents.update'), [
                'nib_number' => '1234567890123',
                'ktp_image' => UploadedFile::fake()->createWithContent('ktp.png', $this->pixel()),
                'nib_document' => UploadedFile::fake()->createWithContent('nib.pdf', '%PDF-1.4 replacement'),
            ])
            ->assertRedirect(route('provider.verification'));

        $profile->refresh();

        Storage::disk('provider_documents')->assertExists($profile->ktp_image);
        Storage::disk('provider_documents')->assertExists($profile->nib_document);
        Storage::disk('public')->assertExists('provider/documents/legacy-ktp.jpg');
        Storage::disk('public')->assertExists('provider/documents/legacy-nib.pdf');
    }

    public function test_sensitive_uploads_require_an_allowed_original_extension_in_addition_to_content_type(): void
    {
        $owner = $this->provider();
        $owner->providerProfile()->update([
            'ktp_image' => 'already-private/ktp.png',
            'nib_number' => '1234567890123',
            'nib_document' => 'already-private/nib.pdf',
            'business_image' => 'already-public/business.png',
        ]);

        $this->actingAs($owner, 'provider')
            ->post(route('provider.profile.documents.update'), [
                'nib_number' => '1234567890123',
                'ktp_image' => UploadedFile::fake()->createWithContent('ktp.txt', $this->pixel()),
            ])
            ->assertSessionHasErrors('ktp_image');

        $this->actingAs($owner, 'provider')
            ->post(route('provider.profile.documents.update'), [
                'nib_number' => '1234567890123',
                'nib_document' => UploadedFile::fake()->createWithContent('nib.txt', '%PDF-1.4 disguised'),
            ])
            ->assertSessionHasErrors('nib_document');
    }

    public function test_multi_document_failure_keeps_old_files_and_cleans_staged_objects(): void
    {
        [$owner, $profile] = $this->providerWithDocuments();
        $oldKtp = $profile->ktp_image;
        $oldNib = $profile->nib_document;
        $stagedKtp = "providers/{$owner->id}/ktp/staged.png";

        $documents = Mockery::mock(ProviderDocumentStorage::class)->makePartial();
        $documents->shouldReceive('stage')
            ->once()
            ->ordered()
            ->andReturnUsing(function () use ($stagedKtp): string {
                Storage::disk('provider_documents')->put($stagedKtp, 'staged ktp');

                return $stagedKtp;
            });
        $documents->shouldReceive('stage')
            ->once()
            ->ordered()
            ->andThrow(new RuntimeException('Simulated second document storage failure.'));
        $this->app->instance(ProviderDocumentStorage::class, $documents);

        $this->actingAs($owner, 'provider')
            ->post(route('provider.profile.documents.update'), [
                'nib_number' => '1234567890123',
                'ktp_image' => UploadedFile::fake()->createWithContent('ktp.png', $this->pixel()),
                'nib_document' => UploadedFile::fake()->createWithContent('nib.pdf', '%PDF-1.4 replacement'),
            ])
            ->assertSessionHas('error');

        $profile->refresh();
        $this->assertSame($oldKtp, $profile->ktp_image);
        $this->assertSame($oldNib, $profile->nib_document);
        Storage::disk('provider_documents')->assertExists($oldKtp);
        Storage::disk('provider_documents')->assertExists($oldNib);
        Storage::disk('provider_documents')->assertMissing($stagedKtp);
    }

    /** @return array{0: User, 1: ProviderProfile} */
    private function providerWithDocuments(): array
    {
        $owner = $this->provider();
        $profile = $owner->providerProfile()->firstOrFail();
        $profile->update([
            'ktp_image' => "providers/{$owner->id}/ktp/private.jpg",
            'nib_number' => '1234567890123',
            'nib_document' => "providers/{$owner->id}/nib/private.pdf",
            'business_image' => 'provider/documents/business.jpg',
        ]);

        Storage::disk('provider_documents')->put($profile->ktp_image, 'private ktp');
        Storage::disk('provider_documents')->put($profile->nib_document, 'private nib');
        Storage::disk('public')->put($profile->business_image, 'public business image');

        return [$owner, $profile->refresh()];
    }

    private function provider(): User
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'pending',
        ]);

        return $provider;
    }

    private function branch(User $owner): ProviderBranch
    {
        return ProviderBranch::query()->create([
            'provider_id' => $owner->id,
            'branch_name' => 'Document Security Branch',
            'email' => "document-branch-{$owner->id}@example.test",
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Dokumen Privat',
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

    /** @param array<int, string> $permissions */
    private function branchAccount(User $owner, ProviderBranch $branch, array $permissions): User
    {
        $role = ProviderRole::query()->create([
            'provider_id' => $owner->id,
            'branch_id' => $branch->id,
            'role_name' => 'Document Branch Role',
            'slug' => "document-branch-role-{$branch->id}",
            'status' => 'active',
        ]);

        $role->menuPermissions()->createMany(
            collect($permissions)->map(fn (string $menuKey) => ['menu_key' => $menuKey])->all(),
        );

        return User::factory()->create([
            'role' => 'provider',
            'provider_id' => $owner->id,
            'branch_id' => $branch->id,
            'provider_role_id' => $role->id,
        ]);
    }

    private function pixel(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
    }
}

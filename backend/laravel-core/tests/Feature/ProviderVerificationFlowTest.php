<?php

namespace Tests\Feature;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProviderVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_logs_provider_in_and_sends_them_to_verification(): void
    {
        $response = $this
            ->withSession([])
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson(route('api.auth.register-provider'), [
                'first_name' => 'Ayu',
                'last_name' => 'Salon',
                'username' => 'ayusalon',
                'email' => 'ayu@example.test',
                'country_code' => '+62',
                'phone_number' => '81234567890',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('redirect_url', route('provider.verification', [], false));

        $provider = User::query()->where('email', 'ayu@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($provider, 'provider');
        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $provider->id,
            'status' => 'inactive',
            'document_status' => 'pending',
            'trial_starts_at' => null,
            'trial_ends_at' => null,
        ]);
    }

    public function test_unverified_provider_is_redirected_to_verification_and_operational_routes_are_locked(): void
    {
        $provider = $this->provider('pending');

        $this->actingAs($provider, 'provider')
            ->get(route('provider.dashboard'))
            ->assertRedirect(route('provider.verification'));

        $this->actingAs($provider, 'provider')
            ->get(route('provider.services.index'))
            ->assertRedirect(route('provider.verification'));

        $this->actingAs($provider, 'provider')
            ->get(route('provider.verification'))
            ->assertOk()
            ->assertSee('Verifikasi usaha Anda')
            ->assertSee('dokumen NIB')
            ->assertSee('0/3')
            ->assertSee('Belum diunggah')
            ->assertSee('provider-verification.js')
            ->assertSee('Menu masih dikunci');
    }

    public function test_provider_can_submit_complete_ktp_nib_and_business_documents(): void
    {
        Storage::fake('public');
        Storage::fake('provider_documents');
        $provider = $this->provider('pending');
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $response = $this->actingAs($provider, 'provider')
            ->post(route('provider.profile.documents.update'), [
                'nib_number' => '1234567890123',
                'ktp_image' => UploadedFile::fake()->createWithContent('ktp.png', $pixel),
                'nib_document' => UploadedFile::fake()->createWithContent('nib.pdf', '%PDF-1.4 test'),
                'business_image' => UploadedFile::fake()->createWithContent('usaha.png', $pixel),
            ]);

        $response->assertRedirect(route('provider.verification'));

        $profile = $provider->providerProfile()->firstOrFail();
        $this->assertSame('submitted', $profile->document_status);
        $this->assertSame('1234567890123', $profile->nib_number);
        $this->assertNotNull($profile->document_submitted_at);
        Storage::disk('provider_documents')->assertExists($profile->ktp_image);
        Storage::disk('provider_documents')->assertExists($profile->nib_document);
        Storage::disk('public')->assertExists($profile->business_image);

        $this->actingAs($provider, 'provider')
            ->get(route('provider.verification'))
            ->assertOk()
            ->assertSee('3/3')
            ->assertSee('Semua dokumen sudah tersimpan')
            ->assertSee('Sudah diunggah')
            ->assertSee('Lihat dokumen yang tersimpan');
    }

    public function test_admin_cannot_verify_incomplete_documents_and_can_approve_complete_submission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $provider = $this->provider('submitted');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.providers.document-status', $provider), [
                'document_status' => 'verified',
            ])
            ->assertSessionHasErrors('document_status');

        $profile = $provider->providerProfile()->firstOrFail();
        $profile->update([
            'ktp_image' => 'provider/documents/ktp.jpg',
            'nib_number' => '1234567890123',
            'nib_document' => 'provider/documents/nib.pdf',
            'business_image' => 'provider/documents/usaha.jpg',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.providers.document-status', $provider), [
                'document_status' => 'verified',
            ])
            ->assertSessionHas('success');

        $profile->refresh();
        $this->assertSame('verified', $profile->document_status);
        $this->assertSame('active', $profile->status);
        $this->assertNotNull($profile->document_verified_at);

        $this->actingAs($provider, 'provider')
            ->get(route('provider.dashboard'))
            ->assertOk();
    }

    public function test_verified_free_provider_is_visible_without_subscription_or_trial(): void
    {
        $provider = $this->provider('verified', 'active');

        $branch = ProviderBranch::query()->create([
            'provider_id' => $provider->id,
            'branch_name' => 'Salon Gratis Terverifikasi',
            'email' => 'branch@example.test',
            'phone_code' => '+62',
            'phone_number' => '81234567890',
            'address' => 'Jl. Melati No. 1',
            'country_id' => 'Indonesia',
            'state_id' => 'Jawa Barat',
            'city_id' => 'Bandung',
            'zip_code' => '40111',
            'working_start_hour' => '09:00',
            'working_end_hour' => '17:00',
            'working_days' => ['monday'],
            'holidays' => [],
            'status' => 'active',
        ]);

        $this->assertFalse($provider->providerProfile->hasActiveSubscription());
        $this->assertTrue(
            ProviderBranch::query()->visibleToCustomer()->whereKey($branch->id)->exists()
        );
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

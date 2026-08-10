<?php

namespace Tests\Feature;

use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderServiceCodeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_code_and_slug_are_unique_per_provider(): void
    {
        $firstProvider = $this->verifiedProvider();
        $secondProvider = $this->verifiedProvider();

        $this->service($firstProvider, 'Layanan Provider A', 'layanan-sama', '1001');
        $this->service($secondProvider, 'Layanan Provider B', 'layanan-sama', '1001');

        $this->assertDatabaseCount('services', 2);

        $this->actingAs($firstProvider, 'provider')
            ->from(route('provider.services.create'))
            ->post(route('provider.services.continue.information'), [
                'title' => 'Layanan Baru',
                'code' => ' 1001 ',
                'category' => 'Spa & Pijat',
                'price' => 12000,
            ])
            ->assertRedirect(route('provider.services.create'))
            ->assertSessionHasErrors('code');

        $this->actingAs($secondProvider, 'provider')
            ->post(route('provider.services.continue.information'), [
                'title' => 'Layanan Lain',
                'code' => '1002',
                'category' => 'Spa & Pijat',
                'price' => 12000,
            ])
            ->assertRedirect(provider_route('provider.services.create', ['step' => 'branch']))
            ->assertSessionHas('service_draft.code', '1002');
    }

    private function service(User $provider, string $title, string $slug, string $code): Service
    {
        return Service::create([
            'provider_id' => $provider->id,
            'title' => $title,
            'slug' => $slug,
            'category' => 'Spa & Pijat',
            'code' => $code,
            'price' => 12000,
            'status' => 'active',
            'verify_status' => 'pending',
        ]);
    }

    private function verifiedProvider(): User
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        return $provider;
    }
}

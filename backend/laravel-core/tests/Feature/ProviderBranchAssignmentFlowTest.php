<?php

namespace Tests\Feature;

use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProviderBranchAssignmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_is_created_in_one_step_and_staff_assignment_lives_in_add_staff(): void
    {
        Storage::fake('public');
        $provider = $this->verifiedProvider();

        $this->actingAs($provider, 'provider')
            ->get(route('provider.branch.create'))
            ->assertOk()
            ->assertSee('Save Branch')
            ->assertDontSee('provider-branch-form-tabs', false)
            ->assertDontSee('data-setup-branch-staff', false);

        $this->actingAs($provider, 'provider')
            ->post(route('provider.branch.store'), [
                'branch_name' => 'Cabang Utama',
                'email' => 'cabang@example.test',
                'phone_code' => '+62',
                'phone_number' => '81234567890',
                'address' => 'Jalan Utama No. 1',
                'country_id' => 'Indonesia',
                'state_id' => 'Bali',
                'city_id' => 'Denpasar',
                'zip_code' => '80111',
                'working_start_hour' => '09:00',
                'working_end_hour' => '18:00',
                'working_days' => ['Monday', 'Tuesday'],
                'holidays' => [],
                'images' => [UploadedFile::fake()->createWithContent(
                    'branch.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
                )],
            ])
            ->assertRedirect(route('provider.branch.index'));

        $this->assertDatabaseHas('provider_branches', [
            'provider_id' => $provider->id,
            'branch_name' => 'Cabang Utama',
        ]);

        $this->actingAs($provider, 'provider')
            ->get(route('provider.staffs.index'))
            ->assertOk()
            ->assertSee('Work Location (Branch)')
            ->assertSee('name="branch_id"', false)
            ->assertSee('This staff member will work and receive bookings at the selected branch.');
    }

    private function verifiedProvider(): User
    {
        $provider = User::factory()->create([
            'role' => 'provider',
        ]);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        return $provider;
    }
}

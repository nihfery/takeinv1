<?php

namespace Tests\Feature;

use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_update_religion_and_allergies_on_profile(): void
    {
        $customer = User::factory()->create([
            'name' => 'Customer Lama',
            'email' => 'customer-lama@example.test',
            'role' => 'customer',
        ]);

        CustomerProfile::create([
            'user_id' => $customer->id,
            'phone_number' => '081234567890',
            'gender' => 'female',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->putJson(route('api.customer.profile.update'), [
                'name' => 'Customer Baru',
                'email' => 'customer-baru@example.test',
                'phone_number' => '081298765432',
                'gender' => 'female',
                'date_of_birth' => '1998-04-12',
                'religion' => 'Islam',
                'allergies' => 'Alergi pewarna rambut',
                'address_line_1' => 'Jl. Melati No. 10',
                'city' => 'Bandung',
                'state' => 'Jawa Barat',
                'country' => 'Indonesia',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Customer Baru')
            ->assertJsonPath('data.customer_profile.religion', 'Islam')
            ->assertJsonPath('data.customer_profile.allergies', 'Alergi pewarna rambut');

        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $customer->id,
            'religion' => 'Islam',
            'allergies' => 'Alergi pewarna rambut',
        ]);
    }
}

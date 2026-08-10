<?php

namespace Tests\Feature;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_category_endpoint_returns_seeded_hierarchy(): void
    {
        $this->getJson('/api/categories?hierarchy=1')
            ->assertOk()
            ->assertJsonPath('data.3.name', 'Hair Salon')
            ->assertJsonPath('data.3.children.1.name', 'Hair Wash');
    }

    public function test_provider_selection_stores_the_leaf_category_name_in_the_draft(): void
    {
        $provider = $this->verifiedProvider();
        $hairSalon = ServiceCategory::where('slug', 'hair-salon')->firstOrFail();
        $hairWash = ServiceCategory::where('slug', 'hair-wash')->firstOrFail();

        $this->actingAs($provider, 'provider')
            ->post(route('provider.services.continue.information'), [
                'title' => 'Premium Hair Wash',
                'category_group_id' => $hairSalon->id,
                'category_id' => $hairWash->id,
                'category' => 'Forged category name',
                'price' => 85000,
            ])
            ->assertRedirect(provider_route('provider.services.create', ['step' => 'branch']))
            ->assertSessionHas('service_draft.category', 'Hair Wash')
            ->assertSessionHas('service_draft.category_id', $hairWash->id);
    }

    public function test_hair_wash_category_filters_partners_that_offer_hair_wash(): void
    {
        $provider = $this->verifiedProvider();
        $branch = ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => 'Hair Wash Studio',
            'email' => 'hair-wash@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Hair Wash No. 1',
            'country_id' => 'Indonesia',
            'state_id' => 'Bali',
            'city_id' => 'Denpasar',
            'zip_code' => '80111',
            'working_start_hour' => '09:00',
            'working_end_hour' => '18:00',
            'working_days' => ['monday'],
            'holidays' => [],
            'status' => 'active',
        ]);
        $hairWash = ServiceCategory::where('slug', 'hair-wash')->firstOrFail();

        Service::create([
            'provider_id' => $provider->id,
            'title' => 'Hair Wash Treatment',
            'slug' => 'hair-wash-treatment',
            'category' => $hairWash->name,
            'category_id' => $hairWash->id,
            'price' => 85000,
            'branch_ids' => [$branch->id],
            'status' => 'active',
            'verify_status' => 'verified',
        ]);

        $this->getJson('/api/branches?category=Hair Wash')
            ->assertOk()
            ->assertJsonPath('data.0.id', $branch->id)
            ->assertJsonPath('data.0.services.0.category_name', 'Hair Wash')
            ->assertJsonPath('data.0.services.0.main_category_name', 'Hair Salon');
    }

    public function test_public_service_list_and_search_only_return_services_with_an_active_leaf_and_active_parent(): void
    {
        $provider = $this->verifiedProvider();
        $hairSalon = ServiceCategory::where('slug', 'hair-salon')->firstOrFail();
        $hairWash = ServiceCategory::where('slug', 'hair-wash')->firstOrFail();
        $inactiveLeaf = ServiceCategory::create([
            'parent_id' => $hairSalon->id,
            'name' => 'Inactive Leaf',
            'slug' => 'inactive-leaf',
            'status' => 'inactive',
        ]);
        $inactiveParent = ServiceCategory::create([
            'name' => 'Inactive Parent',
            'slug' => 'inactive-parent',
            'status' => 'inactive',
        ]);
        $leafWithInactiveParent = ServiceCategory::create([
            'parent_id' => $inactiveParent->id,
            'name' => 'Orphaned Active Leaf',
            'slug' => 'orphaned-active-leaf',
            'status' => 'active',
        ]);
        $nonLeaf = ServiceCategory::create([
            'parent_id' => $hairSalon->id,
            'name' => 'Intermediate Category',
            'slug' => 'intermediate-category',
            'status' => 'active',
        ]);
        $nestedLeaf = ServiceCategory::create([
            'parent_id' => $nonLeaf->id,
            'name' => 'Nested Leaf',
            'slug' => 'nested-leaf',
            'status' => 'active',
        ]);

        $visible = $this->createService($provider, $hairWash, 'catalog-visible');
        $hiddenServices = [
            $this->createService($provider, null, 'catalog-uncategorized', 'Hair Wash'),
            $this->createService($provider, $hairSalon, 'catalog-root-only'),
            $this->createService($provider, $inactiveLeaf, 'catalog-inactive-leaf'),
            $this->createService($provider, $leafWithInactiveParent, 'catalog-inactive-parent'),
            $this->createService($provider, $nonLeaf, 'catalog-non-leaf'),
            $this->createService($provider, $nestedLeaf, 'catalog-level-three'),
        ];

        foreach (['/api/services?search=Catalog', '/api/services?category=hair-wash'] as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.id', $visible->id)
                ->assertJsonPath('data.0.category_id', $hairWash->id)
                ->assertJsonPath('data.0.category_name', 'Hair Wash')
                ->assertJsonPath('data.0.category_slug', 'hair-wash')
                ->assertJsonPath('data.0.main_category_name', 'Hair Salon')
                ->assertJsonPath('data.0.main_category_slug', 'hair-salon');
        }

        $this->getJson("/api/services/{$visible->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $visible->id)
            ->assertJsonPath('data.category_slug', 'hair-wash')
            ->assertJsonPath('data.main_category_slug', 'hair-salon');

        foreach ($hiddenServices as $hiddenService) {
            $this->getJson("/api/services/{$hiddenService->id}")->assertNotFound();
        }

        $this->getJson('/api/categories?hierarchy=1')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'intermediate-category'])
            ->assertJsonMissing(['slug' => 'nested-leaf']);
    }

    public function test_public_branch_results_only_include_matching_branch_services_with_valid_taxonomy(): void
    {
        $provider = $this->verifiedProvider();
        $matchingBranch = $this->createBranch($provider, 'Matching Hair Branch');
        $otherBranch = $this->createBranch($provider, 'Other Provider Branch');
        $hairSalon = ServiceCategory::where('slug', 'hair-salon')->firstOrFail();
        $hairWash = ServiceCategory::where('slug', 'hair-wash')->firstOrFail();

        $visible = $this->createService($provider, $hairWash, 'matching-hair-wash', null, [$matchingBranch->id]);
        $legacyOnly = $this->createService($provider, null, 'matching-legacy-only', 'Hair Wash', [$matchingBranch->id]);
        $rootOnly = $this->createService($provider, $hairSalon, 'matching-root-only', null, [$matchingBranch->id]);

        $staff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'first_name' => 'Taxonomy',
            'last_name' => 'Tester',
            'email' => 'taxonomy-staff@example.test',
            'gender' => 'female',
            'branch_id' => $matchingBranch->id,
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $staff->skills()->attach([$visible->id, $legacyOnly->id, $rootOnly->id]);

        $legacyProvider = $this->verifiedProvider();
        $legacyOnlyBranch = $this->createBranch($legacyProvider, 'Legacy Only Branch');
        $this->createService($legacyProvider, null, 'legacy-only-hair-wash', 'Hair Wash', [$legacyOnlyBranch->id]);

        $this->getJson('/api/branches?category=hair-wash&per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $matchingBranch->id)
            ->assertJsonPath('data.0.services_count', 1)
            ->assertJsonCount(1, 'data.0.services')
            ->assertJsonPath('data.0.services.0.id', $visible->id)
            ->assertJsonPath('data.0.services.0.category_name', 'Hair Wash')
            ->assertJsonPath('data.0.services.0.main_category_name', 'Hair Salon');

        $this->getJson("/api/branches/{$matchingBranch->id}/services")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonCount(1, 'grouped')
            ->assertJsonPath('grouped.0.category', 'Hair Wash')
            ->assertJsonCount(1, 'grouped.0.services');

        $this->getJson("/api/branches/{$matchingBranch->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.staff.0.skills')
            ->assertJsonPath('data.staff.0.skills.0.id', $visible->id);

        $this->getJson("/api/branches/{$matchingBranch->id}/staff")
            ->assertOk()
            ->assertJsonCount(1, 'data.0.skills')
            ->assertJsonPath('data.0.skills.0.id', $visible->id);

        $this->assertNotSame($matchingBranch->id, $otherBranch->id);
    }

    private function createBranch(User $provider, string $name): ProviderBranch
    {
        $slug = str($name)->slug();

        return ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => $name,
            'email' => "{$slug}@example.test",
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => "Jl. {$name} No. 1",
            'country_id' => 'Indonesia',
            'state_id' => 'Bali',
            'city_id' => 'Denpasar',
            'zip_code' => '80111',
            'working_start_hour' => '09:00',
            'working_end_hour' => '18:00',
            'working_days' => ['monday'],
            'holidays' => [],
            'status' => 'active',
        ]);
    }

    private function createService(
        User $provider,
        ?ServiceCategory $category,
        string $slug,
        ?string $legacyCategory = null,
        array $branchIds = [],
    ): Service {
        return Service::create([
            'provider_id' => $provider->id,
            'title' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'category' => $legacyCategory ?? $category?->name,
            'category_id' => $category?->id,
            'price' => 85000,
            'branch_ids' => $branchIds,
            'status' => 'active',
            'verify_status' => 'verified',
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

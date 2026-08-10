<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Review\Infrastructure\Persistence\Models\BranchReview;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Review\Infrastructure\Persistence\Models\StaffReview;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_navigation_uses_primary_groups_and_real_submenus(): void
    {
        $provider = $this->verifiedProvider();

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Main navigation"', false)
            ->assertSee('Overview')
            ->assertSee('Appointments')
            ->assertSee('Business')
            ->assertSee('Customers')
            ->assertSee('Finance')
            ->assertSee('data-sidebar-tooltips', false)
            ->assertSee('data-theme-value="light"', false)
            ->assertSee('data-theme-value="dark"', false)
            ->assertSee('<span class="sidebar-hover-label" aria-hidden="true">Dashboard</span>', false)
            ->assertDontSee('Marketing')
            ->assertDontSee('Chatbot');

        $labels = ProviderMenuAccess::labels();

        $this->assertSame('Customer Directory', $labels['customers']);
        $this->assertSame('Transactions', $labels['payments']);
        $this->assertSame('Roles & Permissions', $labels['roles_permissions']);
    }

    public function test_provider_can_open_customer_directory_and_reviews(): void
    {
        $provider = $this->verifiedProvider();

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.customers.index'))
            ->assertOk()
            ->assertSee('Customer directory')
            ->assertSee('No customers found');

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.reviews.index'))
            ->assertOk()
            ->assertSee('Location reviews')
            ->assertSee('Professional reviews');
    }

    public function test_each_primary_menu_renders_only_its_submenu_context(): void
    {
        $provider = $this->verifiedProvider();

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.bookings.index'))
            ->assertOk()
            ->assertSee('aria-label="Bookings"', false)
            ->assertSee('aria-label="Calendar"', false)
            ->assertSee('aria-label="Queue"', false)
            ->assertSee('aria-label="Walk-in"', false)
            ->assertDontSee('aria-label="Services"', false);

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.calendar.index'))
            ->assertOk()
            ->assertSee('provider-calendar-consistent-page', false)
            ->assertSee('provider-calendar-google-navigation', false)
            ->assertSee('provider-calendar-view-tabs', false)
            ->assertSee('provider-resource-scheduler is-day is-single-day', false)
            ->assertSee('class="active">Day</a>', false)
            ->assertDontSee('<h1 class="provider-order-page-title">Calendar</h1>', false)
            ->assertDontSee('provider-calendar-summary-grid', false)
            ->assertDontSee('class="admin-breadcrumb"', false);

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.services.index'))
            ->assertOk()
            ->assertSee('aria-label="Services"', false)
            ->assertSee('aria-label="Team"', false)
            ->assertSee('aria-label="Skills"', false)
            ->assertSee('aria-label="Work schedules"', false)
            ->assertSee('aria-label="Locations"', false)
            ->assertDontSee('aria-label="Queue"', false);

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.payments.index'))
            ->assertOk()
            ->assertSee('aria-label="Transactions"', false)
            ->assertDontSee('aria-label="Skills"', false);
    }

    public function test_branch_sidebar_renders_contextual_tooltip_labels(): void
    {
        $provider = $this->verifiedProvider();
        $branch = $this->branch($provider);
        $role = ProviderRole::create([
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'role_name' => 'Branch operator',
            'slug' => 'branch-operator',
            'status' => 'active',
        ]);

        $role->menuPermissions()->createMany(
            collect(['bookings', 'calendar', 'queue', 'walk_in'])
                ->map(fn (string $menuKey) => ['menu_key' => $menuKey])
                ->all()
        );

        $branchAccount = User::factory()->create([
            'role' => 'provider',
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'provider_role_id' => $role->id,
        ]);

        $this
            ->actingAs($branchAccount, 'provider_branch')
            ->get(route('provider-branch.bookings.index'))
            ->assertOk()
            ->assertSee('id="gridViewContainer"', false)
            ->assertSee('id="listViewContainer"', false)
            ->assertSee('data-booking-view="grid"', false)
            ->assertSee('data-booking-view="list"', false)
            ->assertSee('aria-controls="gridViewContainer"', false)
            ->assertSee('aria-controls="listViewContainer"', false)
            ->assertSee('aria-label="Search bookings"', false)
            ->assertSee('aria-label="Sort appointment ascending"', false)
            ->assertSee('sort-chevron sort-chevron-down active', false)
            ->assertSee('<col class="provider-booking-col-code">', false)
            ->assertSee('<col class="provider-booking-col-appointment">', false)
            ->assertSee('<col class="provider-booking-col-customer">', false)
            ->assertSee('<col class="provider-booking-col-service">', false)
            ->assertSee('<col class="provider-booking-col-payment">', false)
            ->assertSee('<col class="provider-booking-col-status">', false)
            ->assertSee('<col class="provider-booking-col-action">', false)
            ->assertSee('scope="col" class="provider-booking-th-action"', false)
            ->assertSee('colspan="7"', false)
            ->assertSee('provider/js/bookings.js', false)
            ->assertSee('data-sidebar-tooltips', false)
            ->assertSee('data-sidebar-tooltip="Bookings"', false)
            ->assertSee('data-sidebar-tooltip="Calendar"', false)
            ->assertSee('data-sidebar-tooltip="Queue"', false)
            ->assertSee('data-sidebar-tooltip="Walk-in"', false)
            ->assertSee('data-sidebar-tooltip="Logout"', false)
            ->assertSee('<span class="sidebar-hover-label" aria-hidden="true">Calendar</span>', false)
            ->assertSee('<span class="sidebar-hover-label" aria-hidden="true">Logout</span>', false);

        $this
            ->actingAs($branchAccount, 'provider_branch')
            ->get(route('provider-branch.calendar.index'))
            ->assertOk()
            ->assertSee('provider-calendar-consistent-page', false)
            ->assertSee('provider-calendar-google-navigation', false)
            ->assertSee('provider-calendar-view-tabs', false)
            ->assertDontSee('<h1 class="provider-order-page-title">Calendar</h1>', false)
            ->assertDontSee('provider-calendar-summary-grid', false)
            ->assertDontSee('class="admin-breadcrumb"', false);
    }

    public function test_customer_navigation_pages_render_provider_scoped_data(): void
    {
        $provider = $this->verifiedProvider();
        $customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Nadia Customer',
            'email' => 'nadia@example.test',
        ]);
        $profile = CustomerProfile::create([
            'user_id' => $customer->id,
            'phone_number' => '081234567890',
            'city' => 'Bandung',
            'status' => 'active',
        ]);
        $branch = $this->branch($provider);
        $staff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'branch_id' => $branch->id,
            'first_name' => 'Fajar',
            'last_name' => 'Hidayat',
            'email' => 'fajar@example.test',
            'gender' => 'male',
            'role' => 'Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $booking = Booking::create([
            'booking_code' => 'NAV-BOOKING-001',
            'booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'estimated_end_time' => '11:00',
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'booking_type' => 'scheduled',
            'total_price' => 150000,
            'total_duration' => 60,
            'participant_count' => 1,
            'status' => 'completed',
        ]);

        BranchReview::create([
            'booking_id' => $booking->id,
            'rating' => 5,
            'comment' => 'Tempatnya bersih dan nyaman.',
        ]);
        StaffReview::create([
            'booking_id' => $booking->id,
            'staff_id' => $staff->id,
            'rating' => 4,
            'comment' => 'Pelayanannya sangat teliti.',
        ]);

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.customers.index'))
            ->assertOk()
            ->assertSee('Nadia Customer')
            ->assertSee($profile->customer_id)
            ->assertSee('081234567890')
            ->assertSee('Rp150.000');

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.reviews.index'))
            ->assertOk()
            ->assertSee('Tempatnya bersih dan nyaman.')
            ->assertSee('Pelayanannya sangat teliti.')
            ->assertSee('Fajar Hidayat')
            ->assertSee('NAV-BOOKING-001');
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

    private function branch(User $provider): ProviderBranch
    {
        return ProviderBranch::create([
            'provider_id' => $provider->id,
            'branch_name' => 'Pesona Bandung',
            'email' => 'pesona-bandung@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Pesona No. 84',
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
}

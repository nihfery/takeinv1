<?php

namespace Database\Seeders;

use App\Modules\Booking\Application\Services\BookingFlowService;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notification\Infrastructure\Persistence\Models\AppNotification;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Promotion\Infrastructure\Persistence\Models\Coupon;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRoleMenuPermission;
use App\Modules\Review\Infrastructure\Persistence\Models\BranchReview;
use App\Modules\Review\Infrastructure\Persistence\Models\StaffReview;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Staff\Infrastructure\Persistence\Models\StaffSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalonDirectorySeeder extends Seeder
{
    /** Number of salon brands (each brand = 1 central provider account). */
    private const BRANDS = 20;

    /** Branches (cabang) created per brand, spread across different cities. */
    private const BRANCHES_PER_BRAND = 5;

    /**
     * Salon brands. Each entry: [brandName, salonType].
     * One brand becomes a single provider (akun pusat) that owns several branches.
     */
    private array $brandDefs = [
        ['Cantika', 'Beauty Salon'],
        ['Aura', 'Hair Studio'],
        ['Bella', 'Salon & Spa'],
        ['Kirana', 'Beauty Clinic'],
        ['Diva', 'Glamour Studio'],
        ['Lavender', 'Beauty House'],
        ['Mahkota', 'Salon'],
        ['Permata', 'Beauty Salon'],
        ['Sasha', 'Hair Studio'],
        ['Anggun', 'Salon & Spa'],
        ['Citra', 'Beauty Clinic'],
        ['Nirmala', 'Spa & Salon'],
        ['Puspita', 'Nail Art Studio'],
        ['Maharani', 'Beauty House'],
        ['Luna', 'Hair Studio'],
        ['Kartika', 'Beauty Salon'],
        ['Pesona', 'Salon & Spa'],
        ['Intan', 'Barbershop'],
        ['Zahra', 'Beauty Clinic'],
        ['Naura', 'Salon'],
    ];

    /** Indonesian cities with province and approximate coordinates. */
    private array $cities = [
        ['Jakarta', 'DKI Jakarta', -6.2088, 106.8456],
        ['Bandung', 'Jawa Barat', -6.9175, 107.6191],
        ['Surabaya', 'Jawa Timur', -7.2575, 112.7521],
        ['Yogyakarta', 'DI Yogyakarta', -7.7956, 110.3695],
        ['Denpasar', 'Bali', -8.6705, 115.2126],
        ['Medan', 'Sumatera Utara', 3.5952, 98.6722],
        ['Semarang', 'Jawa Tengah', -6.9667, 110.4167],
        ['Makassar', 'Sulawesi Selatan', -5.1477, 119.4327],
        ['Palembang', 'Sumatera Selatan', -2.9761, 104.7754],
        ['Bekasi', 'Jawa Barat', -6.2383, 106.9756],
        ['Depok', 'Jawa Barat', -6.4025, 106.7942],
        ['Tangerang', 'Banten', -6.1781, 106.6300],
        ['Bogor', 'Jawa Barat', -6.5950, 106.8166],
        ['Malang', 'Jawa Timur', -7.9666, 112.6326],
        ['Batam', 'Kepulauan Riau', 1.0456, 104.0305],
        ['Pekanbaru', 'Riau', 0.5071, 101.4478],
        ['Padang', 'Sumatera Barat', -0.9471, 100.4172],
        ['Balikpapan', 'Kalimantan Timur', -1.2379, 116.8529],
        ['Samarinda', 'Kalimantan Timur', -0.5022, 117.1536],
        ['Manado', 'Sulawesi Utara', 1.4748, 124.8421],
        ['Surakarta', 'Jawa Tengah', -7.5755, 110.8243],
        ['Banjarmasin', 'Kalimantan Selatan', -3.3194, 114.5908],
        ['Pontianak', 'Kalimantan Barat', -0.0263, 109.3425],
        ['Cirebon', 'Jawa Barat', -6.7320, 108.5523],
        ['Bandar Lampung', 'Lampung', -5.3971, 105.2668],
    ];

    /** Branch cover images. */
    private array $images = [
        'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1600948836101-f9ffda59d250?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1552693673-1bf958298935?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1633681926022-84c23e8cb2d6?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=1200&q=80',
    ];

    /**
     * Service categories with their service catalog.
     * Each service: [title, price, estimated_duration_minutes, requires_dp].
     */
    private array $catalog = [
        'Haircut' => [
            ['Potong Rambut Pria', 45000, 30, false],
            ['Potong Rambut Wanita', 70000, 45, false],
            ['Cuci & Blow', 55000, 35, false],
        ],
        'Hair Color' => [
            ['Hair Coloring Full', 350000, 120, true],
            ['Highlight Rambut', 450000, 150, true],
            ['Bleaching Premium', 400000, 140, true],
        ],
        'Hair Treatment' => [
            ['Creambath Relaxing', 120000, 60, false],
            ['Hair Spa Keratin', 180000, 75, false],
            ['Smoothing Rambut', 500000, 180, true],
        ],
        'Facial' => [
            ['Facial Brightening', 180000, 75, false],
            ['Facial Acne Care', 200000, 80, false],
            ['Facial Gold Luxury', 285000, 90, true],
        ],
        'Spa & Pijat' => [
            ['Body Massage', 160000, 90, false],
            ['Aromatherapy Spa', 230000, 120, false],
            ['Refleksi Kaki', 95000, 45, false],
        ],
        'Kuku' => [
            ['Manicure Classic', 90000, 45, false],
            ['Pedicure Spa', 115000, 50, false],
            ['Nail Art Gel', 150000, 75, false],
        ],
        'Makeup' => [
            ['Makeup Natural', 250000, 60, false],
            ['Makeup Wisuda', 300000, 75, false],
            ['Makeup Pesta', 450000, 90, true],
        ],
        'Barbershop' => [
            ['Haircut & Styling', 60000, 35, false],
            ['Cukur Jenggot', 40000, 25, false],
            ['Hair Tattoo Design', 85000, 45, false],
        ],
        'Waxing' => [
            ['Waxing Tangan', 120000, 45, false],
            ['Waxing Kaki', 150000, 60, false],
            ['Full Body Waxing', 400000, 120, true],
        ],
    ];

    private array $staffFirstNames = [
        'Ayu', 'Dimas', 'Rani', 'Bima', 'Sinta', 'Yoga', 'Nadia', 'Raka', 'Maya', 'Ardi',
        'Putri', 'Galih', 'Dewi', 'Fajar', 'Lia', 'Reza', 'Tari', 'Iqbal', 'Vina', 'Bayu',
    ];

    private array $staffLastNames = [
        'Prameswari', 'Saputra', 'Lestari', 'Nugraha', 'Maharani', 'Firmansyah', 'Kusuma',
        'Aditya', 'Wijaya', 'Pratama', 'Anggraini', 'Hidayat', 'Permata', 'Santoso',
    ];

    /**
     * Map a salon type to its specialty service categories.
     * Every branch also gets the core categories defined below.
     */
    private array $typeCategories = [
        'Barbershop' => ['Barbershop', 'Hair Color'],
        'Hair Studio' => ['Hair Color', 'Hair Treatment'],
        'Nail Art Studio' => ['Kuku', 'Makeup'],
        'Beauty Clinic' => ['Makeup', 'Waxing'],
        'Spa & Salon' => ['Spa & Pijat', 'Hair Treatment'],
        'Salon & Spa' => ['Spa & Pijat', 'Kuku'],
        'Beauty House' => ['Makeup', 'Hair Color'],
        'Glamour Studio' => ['Makeup', 'Waxing'],
        'Beauty Salon' => ['Hair Color', 'Kuku'],
        'Salon' => ['Hair Treatment', 'Hair Color'],
    ];

    /** Categories every salon offers regardless of its specialty. */
    private array $coreCategories = ['Haircut', 'Facial'];

    /**
     * Every demo service category is a leaf under one public root category.
     *
     * @var array<string, string>
     */
    private array $categoryParentSlugs = [
        'Haircut' => 'hair-salon',
        'Hair Color' => 'hair-salon',
        'Hair Treatment' => 'hair-salon',
        'Barbershop' => 'hair-salon',
        'Facial' => 'beauty',
        'Makeup' => 'beauty',
        'Spa & Pijat' => 'wellness',
        'Waxing' => 'wellness',
        'Kuku' => 'nail',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->purgeExistingData();
            $categories = $this->ensureCategories();
            $this->ensureDemoCustomer();
            $this->createBrands($categories);
            $this->ensureCoupons($categories);
            $this->createOperationalDemoData();
        });
    }

    /**
     * Remove previously seeded demo/directory data so the directory can be rebuilt cleanly.
     */
    private function purgeExistingData(): void
    {
        $legacyEmails = [
            'provider-pusat@demo.test',
            'beauty-glow-salon@demo.test',
            'queen-hair-studio@demo.test',
            'fresh-cut-barbershop@demo.test',
        ];

        $providerIds = User::query()
            ->where('role', 'provider')
            ->where(function ($query) use ($legacyEmails) {
                $query->where('email', 'like', '%@directory.test')
                    ->orWhere('email', 'like', 'branch-%@demo.test')
                    ->orWhereIn('email', $legacyEmails);
            })
            ->pluck('id')
            ->all();

        if ($providerIds === []) {
            return;
        }

        $staffIds = ProviderStaff::whereIn('provider_id', $providerIds)->pluck('id')->all();
        if ($staffIds !== []) {
            DB::table('staff_skills')->whereIn('staff_id', $staffIds)->delete();
            StaffSchedule::whereIn('staff_id', $staffIds)->delete();
        }

        $roleIds = ProviderRole::whereIn('provider_id', $providerIds)->pluck('id')->all();
        if ($roleIds !== []) {
            ProviderRoleMenuPermission::whereIn('provider_role_id', $roleIds)->delete();
        }

        Payment::whereHas('booking', fn ($query) => $query->whereIn('provider_id', $providerIds))->delete();
        Booking::whereIn('provider_id', $providerIds)->delete();
        ProviderStaff::whereIn('provider_id', $providerIds)->delete();
        Service::whereIn('provider_id', $providerIds)->delete();
        ProviderRole::whereIn('provider_id', $providerIds)->delete();
        // Staff/branch sub-accounts created for a provider also carry provider_id.
        User::whereIn('provider_id', $providerIds)->delete();
        ProviderBranch::whereIn('provider_id', $providerIds)->delete();
        ProviderProfile::whereIn('user_id', $providerIds)->delete();
        User::whereIn('id', $providerIds)->delete();
    }

    /**
     * @return array<string, ServiceCategory>
     */
    private function ensureCategories(): array
    {
        $categories = [];
        $parents = ServiceCategory::query()
            ->roots()
            ->whereIn('slug', array_values(array_unique($this->categoryParentSlugs)))
            ->get()
            ->keyBy('slug');

        foreach (array_keys($this->catalog) as $name) {
            $parentSlug = $this->categoryParentSlugs[$name];
            $parent = $parents->get($parentSlug);

            if (! $parent) {
                throw new \RuntimeException("Required demo taxonomy root [{$parentSlug}] is missing.");
            }

            $categories[$name] = ServiceCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'parent_id' => $parent->id,
                    'name' => $name,
                    'description' => 'Kategori layanan '.$name.' di SalonKu.',
                    'status' => 'active',
                    'is_featured' => in_array($name, ['Haircut', 'Hair Color', 'Facial', 'Spa & Pijat'], true),
                ]
            );
        }

        return $categories;
    }

    private function ensureDemoCustomer(): void
    {
        $customer = User::updateOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Demo Customer',
                'username' => 'customer',
                'password' => Hash::make('customer12345'),
                'role' => 'customer',
                'email_verified_at' => now(),
            ]
        );

        CustomerProfile::updateOrCreate(
            ['user_id' => $customer->id],
            ['phone_number' => '081234567890', 'status' => 'active']
        );
    }

    /**
     * Create salon brands. Each brand is ONE provider (akun pusat) that owns several
     * branches (cabang) across different cities, and each branch has its own account,
     * services, and staff.
     *
     * @param  array<string, ServiceCategory>  $categories
     */
    private function createBrands(array $categories): void
    {
        $cityCount = count($this->cities);
        $imageCount = count($this->images);
        $branchSeq = 0;

        foreach ($this->brandDefs as $brandIndex => [$brandName, $type]) {
            $brandLabel = $brandName.' '.$type;
            $brandSlug = Str::slug($brandLabel);

            $provider = User::updateOrCreate(
                ['email' => 'provider-'.$brandSlug.'@directory.test'],
                [
                    'name' => $brandLabel.' Group',
                    'username' => 'provider-'.$brandSlug,
                    'password' => Hash::make('salon12345'),
                    'role' => 'provider',
                    'provider_id' => null,
                    'branch_id' => null,
                    'provider_role_id' => null,
                    'email_verified_at' => now(),
                ]
            );

            ProviderProfile::updateOrCreate(
                ['user_id' => $provider->id],
                [
                    'phone_number' => '0811'.str_pad((string) (10000000 + $brandIndex), 8, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'document_status' => 'verified',
                    'document_note' => null,
                ]
            );

            for ($b = 0; $b < self::BRANCHES_PER_BRAND; $b++) {
                // Spread a brand's branches across distinct cities.
                $cityIndex = ($brandIndex + $b * self::BRANDS) % $cityCount;
                [$city, $state, $lat, $lng] = $this->cities[$cityIndex];

                $jitterLat = round($lat + (($branchSeq % 7) - 3) * 0.0045, 7);
                $jitterLng = round($lng + (($branchSeq % 5) - 2) * 0.0055, 7);

                // Give every branch 5 distinct photos from the pool (first = cover).
                $gallery = [];
                for ($g = 0; $g < 5; $g++) {
                    $gallery[] = $this->images[($branchSeq + $g) % $imageCount];
                }

                $branch = ProviderBranch::updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'branch_name' => $brandLabel.' - '.$city,
                    ],
                    [
                        'email' => $brandSlug.'-'.Str::slug($city).'@directory.test',
                        'phone_code' => '+62',
                        'phone_number' => '812'.str_pad((string) (30000000 + $branchSeq), 8, '0', STR_PAD_LEFT),
                        'address' => 'Jl. '.$brandName.' No. '.(($branchSeq % 90) + 1).', '.$city,
                        'country_id' => 'Indonesia',
                        'state_id' => $state,
                        'city_id' => $city,
                        'zip_code' => (string) (10000 + (($branchSeq * 7) % 79999)),
                        'latitude' => $jitterLat,
                        'longitude' => $jitterLng,
                        'working_start_hour' => '09:00',
                        'working_end_hour' => '21:00',
                        'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                        'holidays' => [],
                        'image' => $gallery[0],
                        'images' => $gallery,
                        'status' => 'active',
                    ]
                );

                $this->createBranchAccount($provider, $branch, $brandSlug, $city);
                $serviceIds = $this->createServices($provider, $branch, $categories, $type, $branchSeq);
                $this->createStaff($provider, $branch, $categories, $serviceIds, $branchSeq);

                $branchSeq++;
            }
        }
    }

    /**
     * Create a branch (cabang) login account with scoped menu permissions.
     */
    private function createBranchAccount(User $provider, ProviderBranch $branch, string $brandSlug, string $city): void
    {
        $citySlug = Str::slug($city);

        $permissions = collect(ProviderMenuAccess::keys())
            ->reject(fn (string $key) => in_array($key, ['roles_permissions'], true))
            ->values()
            ->all();

        $role = ProviderRole::updateOrCreate(
            [
                'provider_id' => $provider->id,
                'slug' => 'cabang-'.$brandSlug.'-'.$citySlug,
            ],
            [
                'branch_id' => $branch->id,
                'role_name' => 'Admin Cabang '.$city,
                'description' => 'Akun cabang '.$branch->branch_name.'.',
                'status' => 'active',
            ]
        );

        $role->menuPermissions()->delete();
        $role->menuPermissions()->createMany(
            collect($permissions)->map(fn (string $menuKey) => ['menu_key' => $menuKey])->all()
        );

        User::updateOrCreate(
            ['email' => 'cabang-'.$brandSlug.'-'.$citySlug.'@directory.test'],
            [
                'name' => $branch->branch_name,
                'username' => 'cabang-'.$brandSlug.'-'.$citySlug,
                'password' => Hash::make('cabang12345'),
                'role' => 'provider',
                'provider_id' => $provider->id,
                'branch_id' => $branch->id,
                'provider_role_id' => $role->id,
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * Create services for a branch: core categories (offered everywhere) plus the
     * brand-type specialty categories. This keeps category search meaningful while
     * ensuring popular categories like Haircut exist in most cities.
     *
     * @param  array<string, ServiceCategory>  $categories
     * @return array<int> created service ids
     */
    private function createServices(User $provider, ProviderBranch $branch, array $categories, string $type, int $index): array
    {
        $branchCategories = array_values(array_unique(array_merge(
            $this->coreCategories,
            $this->typeCategories[$type] ?? ['Hair Color']
        )));

        $serviceIds = [];
        $s = 0;

        foreach ($branchCategories as $categoryName) {
            $items = $this->catalog[$categoryName] ?? [];
            if ($items === []) {
                continue;
            }

            // Pick 1-2 services from this category (varied by branch).
            $pickCount = 1 + (($index + $s) % 2);
            for ($p = 0; $p < $pickCount; $p++) {
                [$title, $price, $duration, $requiresDp] = $items[($index + $p) % count($items)];
                $category = $categories[$categoryName] ?? null;
                $slug = Str::slug($title.'-'.$branch->id.'-'.$s);

                $service = Service::updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'slug' => $slug,
                    ],
                    [
                        'title' => $title,
                        'category' => $categoryName,
                        'category_id' => $category?->id,
                        'code' => strtoupper(Str::random(8)),
                        'description' => $title.' di '.$branch->branch_name.', '.$branch->city_id.'.',
                        'includes' => 'Konsultasi, layanan utama, dan finishing.',
                        'price_type' => 'fixed',
                        'price' => $price,
                        'minimum_duration' => max(15, $duration - 10),
                        'estimated_duration' => $duration,
                        'maximum_duration' => $duration + 20,
                        'is_queue_enabled' => true,
                        'is_scheduled_enabled' => true,
                        'requires_dp' => $requiresDp,
                        'dp_amount' => $requiresDp ? 50000 : null,
                        'payment_policy' => $requiresDp ? 'Wajib DP untuk layanan ini.' : 'Bayar di tempat tersedia.',
                        'slots' => [],
                        'additional_services' => [],
                        'holidays' => [],
                        'branch_ids' => [(int) $branch->id],
                        'gallery_image' => null,
                        'video_url' => null,
                        'status' => 'active',
                        'verify_status' => 'verified',
                    ]
                );

                $serviceIds[] = $service->id;
                $s++;
            }
        }

        return $serviceIds;
    }

    /**
     * @param  array<string, ServiceCategory>  $categories
     * @param  array<int>  $serviceIds
     */
    private function createStaff(User $provider, ProviderBranch $branch, array $categories, array $serviceIds, int $index): void
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $staffCount = 2 + ($index % 3); // 2-4 staff per salon
        $categoryList = array_values($categories);

        for ($n = 0; $n < $staffCount; $n++) {
            $seq = $index * 4 + $n;
            $firstName = $this->staffFirstNames[$seq % count($this->staffFirstNames)];
            $lastName = $this->staffLastNames[$seq % count($this->staffLastNames)];
            $email = 'staff-'.$branch->id.'-'.$n.'@directory.test';
            $category = $categoryList[$seq % count($categoryList)] ?? null;

            $staff = ProviderStaff::updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'email' => $email,
                ],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => Str::slug($firstName.'-'.$lastName.'-'.$branch->id.'-'.$n),
                    'country_code' => '+62',
                    'phone_number' => '813'.str_pad((string) ($seq + 1), 8, '0', STR_PAD_LEFT),
                    'gender' => $seq % 2 === 0 ? 'female' : 'male',
                    'address' => 'Alamat staf '.$branch->city_id,
                    'country_id' => 'Indonesia',
                    'state_id' => $branch->state_id,
                    'city_id' => $branch->city_id,
                    'postal_code' => $branch->zip_code,
                    'bio' => 'Profesional di '.$branch->branch_name,
                    'category_id' => $category?->id,
                    'branch_id' => $branch->id,
                    'role' => 'staff',
                    'rating' => round(4.4 + (($seq % 6) * 0.1), 1),
                    'current_status' => 'available',
                    'status' => 'active',
                ]
            );

            foreach ($days as $day) {
                StaffSchedule::updateOrCreate(
                    [
                        'staff_id' => $staff->id,
                        'day_of_week' => $day,
                    ],
                    [
                        'start_time' => '09:00',
                        'end_time' => '21:00',
                        'is_available' => true,
                    ]
                );
            }

            $staff->skills()->sync($serviceIds);
        }
    }

    /**
     * Demo coupons for customer checkout, admin coupon management, and coupon validation APIs.
     *
     * @param  array<string, ServiceCategory>  $categories
     */
    private function ensureCoupons(array $categories): void
    {
        $featuredServiceIds = Service::query()
            ->where('status', 'active')
            ->orderBy('price')
            ->limit(12)
            ->pluck('id')
            ->all();

        Coupon::updateOrCreate(
            ['code' => 'NEWUSER'],
            [
                'product_type' => 'all',
                'product_ids' => null,
                'coupon_type' => 'percentage',
                'coupon_value' => 15,
                'quantity' => 500,
                'used_count' => 0,
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
                'status' => 'active',
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'HAIRDAY'],
            [
                'product_type' => 'category',
                'product_ids' => collect(['Haircut', 'Hair Color', 'Hair Treatment'])
                    ->map(fn (string $name) => $categories[$name]->id ?? null)
                    ->filter()
                    ->values()
                    ->all(),
                'coupon_type' => 'fixed',
                'coupon_value' => 25000,
                'quantity' => 250,
                'used_count' => 12,
                'start_date' => now()->subDays(3)->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'status' => 'active',
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'WEEKEND50'],
            [
                'product_type' => 'service',
                'product_ids' => $featuredServiceIds,
                'coupon_type' => 'fixed',
                'coupon_value' => 50000,
                'quantity' => 100,
                'used_count' => 21,
                'start_date' => now()->subDays(14)->toDateString(),
                'end_date' => now()->addWeeks(6)->toDateString(),
                'status' => 'active',
            ]
        );
    }

    private function createOperationalDemoData(): void
    {
        $customer = User::query()->where('email', 'customer@gmail.com')->first();
        $admin = User::query()->where('email', 'admin@gmail.com')->first();

        if (! $customer) {
            return;
        }

        $seedBookings = Booking::query()
            ->where('notes', 'like', 'SalonKu seed:%')
            ->pluck('id')
            ->all();

        if ($seedBookings !== []) {
            BranchReview::whereIn('booking_id', $seedBookings)->delete();
            StaffReview::whereIn('booking_id', $seedBookings)->delete();
            Payment::whereIn('booking_id', $seedBookings)->delete();
            Booking::whereIn('id', $seedBookings)->delete();
        }

        AppNotification::query()
            ->whereIn('type', ['seed.booking', 'seed.provider', 'seed.customer', 'seed.admin'])
            ->delete();

        $bookingFlow = app(BookingFlowService::class);
        $branches = ProviderBranch::query()
            ->with('provider')
            ->where('status', 'active')
            ->orderBy('city_id')
            ->orderBy('branch_name')
            ->limit(12)
            ->get();

        foreach ($branches as $index => $branch) {
            $services = $branch->servicesForBranch()->take(2)->values();

            if ($services->isEmpty()) {
                continue;
            }

            $completedSlot = $this->firstAvailableScheduledSlot(
                $bookingFlow,
                $branch,
                [(int) $services->first()->id],
                $this->futureDate($index + 1)
            );

            if ($completedSlot) {
                $completed = $bookingFlow->createBooking([
                    'branch_id' => $branch->id,
                    'service_ids' => [(int) $services->first()->id],
                    'booking_type' => 'scheduled',
                    'staff_id' => $completedSlot['staff_id'],
                    'booking_date' => $completedSlot['date'],
                    'start_time' => $completedSlot['time'],
                    'payment_type' => 'pay_at_salon',
                    'notes' => 'SalonKu seed: completed booking for '.$branch->branch_name,
                ], $customer);

                $this->markBookingCompleted($completed, now()->subDays($index + 2));
            }

            if ($index < 8) {
                $serviceIds = $services->pluck('id')->map(fn ($id) => (int) $id)->all();
                $upcomingSlot = $this->firstAvailableScheduledSlot(
                    $bookingFlow,
                    $branch,
                    $serviceIds,
                    $this->futureDate($index + 2)
                );

                if ($upcomingSlot) {
                    $bookingFlow->createBooking([
                        'branch_id' => $branch->id,
                        'service_ids' => $serviceIds,
                        'booking_type' => 'scheduled',
                        'staff_id' => $upcomingSlot['staff_id'],
                        'booking_date' => $upcomingSlot['date'],
                        'start_time' => $upcomingSlot['time'],
                        'payment_type' => $index % 2 === 0 ? 'dp' : 'full_payment',
                        'coupon_code' => $index % 2 === 0 ? 'NEWUSER' : null,
                        'notes' => 'SalonKu seed: upcoming booking for '.$branch->branch_name,
                    ], $customer);
                }
            }

            if ($index < 6) {
                try {
                    $bookingFlow->createBooking([
                        'branch_id' => $branch->id,
                        'service_ids' => [$services->first()->id],
                        'booking_type' => 'queue',
                        'booking_date' => now()->toDateString(),
                        'payment_type' => 'pay_at_salon',
                        'customer_name' => 'Walk-in Customer '.($index + 1),
                        'customer_phone' => '0812999'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        'notes' => 'SalonKu seed: queue booking for '.$branch->branch_name,
                    ], null, true);
                } catch (ValidationException $exception) {
                    $outsideOperatingHours = collect($exception->errors()['branch_id'] ?? [])
                        ->contains('Cabang atau Staff sedang di luar jam operasional atau libur saat ini.');

                    if (! $outsideOperatingHours) {
                        throw $exception;
                    }

                    // Queue bookings intentionally mean "right now". Keep the
                    // demo seeder runnable outside salon hours while scheduled
                    // demo bookings above remain deterministic.
                }
            }
        }

        $this->createNotifications($admin, $customer);
    }

    /**
     * @param  array<int>  $serviceIds
     * @return array{date: string, time: string, staff_id: int}|null
     */
    private function firstAvailableScheduledSlot(
        BookingFlowService $bookingFlow,
        ProviderBranch $branch,
        array $serviceIds,
        Carbon $preferredDate
    ): ?array {
        if ($serviceIds === []) {
            return null;
        }

        $services = $bookingFlow->servicesForBooking($branch, $serviceIds, 'scheduled');
        $date = $preferredDate->copy();

        for ($attempt = 0; $attempt < 14; $attempt++) {
            while ($date->isSunday()) {
                $date->addDay();
            }

            $dateString = $date->toDateString();
            $slot = collect($bookingFlow->availableSlots($branch, $services, $dateString))->first();

            if ($slot) {
                return [
                    'date' => $dateString,
                    'time' => (string) $slot['time'],
                    'staff_id' => (int) $slot['staff_id'],
                ];
            }

            $date->addDay();
        }

        return null;
    }

    private function markBookingCompleted(Booking $booking, Carbon $completedAt): void
    {
        $start = $completedAt->copy()->subMinutes((int) ($booking->total_duration ?: 45));

        $booking->payment?->update([
            'amount' => $booking->total_price,
            'status' => 'paid',
            'payment_method' => 'pay_at_salon',
            'paid_at' => $completedAt,
        ]);

        $booking->update([
            'booking_date' => $completedAt->toDateString(),
            'start_time' => $start->format('H:i'),
            'estimated_end_time' => $completedAt->format('H:i'),
            'status' => 'completed',
            'actual_start_time' => $start,
            'actual_end_time' => $completedAt,
            'completed_at' => $completedAt,
        ]);

        BranchReview::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'rating' => 4 + ((int) $booking->id % 2),
                'comment' => 'Layanan rapi, staff ramah, dan proses booking mudah.',
            ]
        );

        if ($booking->staff_id) {
            StaffReview::updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'staff_id' => $booking->staff_id,
                ],
                [
                    'rating' => 4 + ((int) $booking->id % 2),
                    'comment' => 'Staff ramah, teliti, dan membantu selama treatment.',
                ]
            );
        }
    }

    private function createNotifications(?User $admin, User $customer): void
    {
        if ($admin) {
            AppNotification::create([
                'user_id' => $admin->id,
                'type' => 'seed.admin',
                'title' => 'Seed demo siap',
                'body' => 'Directory salon, provider, cabang, staff, booking, dan kupon demo sudah tersedia.',
                'url' => route('admin.dashboard', [], false),
                'data' => ['source' => 'SalonDirectorySeeder'],
            ]);
        }

        AppNotification::create([
            'user_id' => $customer->id,
            'type' => 'seed.customer',
            'title' => 'Activity booking tersedia',
            'body' => 'Booking demo yang sudah selesai tersedia pada halaman activity.',
            'url' => '/activity',
            'data' => ['source' => 'SalonDirectorySeeder'],
        ]);

        User::query()
            ->where('role', 'provider')
            ->whereNull('provider_id')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->each(function (User $provider) {
                AppNotification::create([
                    'user_id' => $provider->id,
                    'type' => 'seed.provider',
                    'title' => 'Cabang demo aktif',
                    'body' => 'Cabang, staff, layanan, dan jadwal demo sudah bisa dikelola.',
                    'url' => route('provider.dashboard', [], false),
                    'data' => ['source' => 'SalonDirectorySeeder'],
                ]);
            });
    }

    private function futureDate(int $daysFromNow): Carbon
    {
        $date = now()->addDays($daysFromNow);

        while ($date->isSunday()) {
            $date->addDay();
        }

        return $date;
    }
}

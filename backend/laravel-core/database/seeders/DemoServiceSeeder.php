<?php

namespace Database\Seeders;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoServiceSeeder extends Seeder
{
    public function run(): void
    {
        $provider = User::where('email', 'provider-pusat@demo.test')->firstOrFail();

        $catalog = [
            ['Haircut Premium', 'Hair', 85000, 40, false],
            ['Hair Wash & Blow', 'Hair', 65000, 35, false],
            ['Creambath Relaxing', 'Hair', 145000, 70, false],
            ['Hair Coloring', 'Hair', 420000, 120, true],
            ['Facial Glow', 'Face & Spa', 225000, 75, true],
            ['Manicure Clean', 'Nail', 115000, 45, false],
            ['Pedicure Spa', 'Nail', 135000, 50, false],
        ];

        ProviderBranch::query()
            ->where('provider_id', $provider->id)
            ->orderBy('branch_name')
            ->get()
            ->each(function (ProviderBranch $branch, int $index) use ($catalog, $provider) {
                $selectedCatalog = collect($catalog)
                    ->slice($index % 3, 4)
                    ->values();

                if ($selectedCatalog->count() < 4) {
                    $selectedCatalog = $selectedCatalog->merge(collect($catalog)->take(4 - $selectedCatalog->count()));
                }

                $selectedCatalog->each(function (array $item) use ($branch, $provider) {
                    [$title, $categoryName, $price, $duration, $requiresDp] = $item;
                    $fullTitle = $title . ' ' . $branch->city_id;
                    $category = ServiceCategory::where('name', $categoryName)->first();

                    Service::updateOrCreate(
                        [
                            'provider_id' => $provider->id,
                            'slug' => Str::slug($fullTitle),
                        ],
                        [
                            'title' => $fullTitle,
                            'category' => $categoryName,
                            'category_id' => $category?->id,
                            'code' => strtoupper(Str::slug($branch->city_id . '-' . $title, '')),
                            'description' => 'Layanan dummy khusus cabang ' . $branch->branch_name . '.',
                            'includes' => 'Konsultasi, pengerjaan layanan, dan finishing.',
                            'price_type' => 'fixed',
                            'price' => $price,
                            'minimum_duration' => max(15, $duration - 10),
                            'estimated_duration' => $duration,
                            'maximum_duration' => $duration + 20,
                            'is_queue_enabled' => true,
                            'is_scheduled_enabled' => true,
                            'requires_dp' => $requiresDp,
                            'dp_amount' => $requiresDp ? 50000 : null,
                            'payment_policy' => 'Data dummy untuk testing cabang.',
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
                });
            });
    }
}

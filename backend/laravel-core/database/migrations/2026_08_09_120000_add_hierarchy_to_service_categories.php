<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('service_categories', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('service_categories')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('service_categories', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
            }
        });

        $now = now();
        $taxonomy = [
            ['name' => 'Nail', 'slug' => 'nail', 'order' => 10, 'children' => [
                ['name' => 'Pedicure', 'slug' => 'pedicure'],
                ['name' => 'Manicure', 'slug' => 'manicure'],
                ['name' => 'Nail Art', 'slug' => 'nail-art'],
                ['name' => 'Nail Extension', 'slug' => 'nail-extension'],
            ]],
            ['name' => 'Wellness', 'slug' => 'wellness', 'order' => 20, 'children' => [
                ['name' => 'Massage & Spa', 'slug' => 'massage-spa'],
                ['name' => 'Waxing', 'slug' => 'waxing'],
                ['name' => 'Scalp Therapy', 'slug' => 'scalp-therapy'],
            ]],
            ['name' => 'Beauty', 'slug' => 'beauty', 'order' => 30, 'children' => [
                ['name' => 'Facial', 'slug' => 'facial'],
                ['name' => 'Eyelash', 'slug' => 'eyelash'],
                ['name' => 'Eyebrow', 'slug' => 'eyebrow'],
                ['name' => 'Nail', 'slug' => 'beauty-nail'],
                ['name' => 'Makeup', 'slug' => 'makeup'],
            ]],
            ['name' => 'Hair Salon', 'slug' => 'hair-salon', 'order' => 40, 'children' => [
                ['name' => 'Haircut', 'slug' => 'haircut'],
                ['name' => 'Hair Wash', 'slug' => 'hair-wash'],
                ['name' => 'Colouring', 'slug' => 'colouring'],
                ['name' => 'Styling', 'slug' => 'styling'],
            ]],
        ];

        foreach ($taxonomy as $group) {
            DB::table('service_categories')->updateOrInsert(
                ['slug' => $group['slug']],
                [
                    'parent_id' => null,
                    'name' => $group['name'],
                    'description' => $group['name'].' services',
                    'status' => 'active',
                    'is_featured' => true,
                    'sort_order' => $group['order'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $parentId = DB::table('service_categories')->where('slug', $group['slug'])->value('id');

            foreach ($group['children'] as $index => $child) {
                DB::table('service_categories')->updateOrInsert(
                    ['slug' => $child['slug']],
                    [
                        'parent_id' => $parentId,
                        'name' => $child['name'],
                        'description' => $child['name'].' services',
                        'status' => 'active',
                        'is_featured' => true,
                        'sort_order' => ($index + 1) * 10,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            if (Schema::hasColumn('service_categories', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }

            if (Schema::hasColumn('service_categories', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};

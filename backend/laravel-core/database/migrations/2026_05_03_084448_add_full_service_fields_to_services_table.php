<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'description')) {
                $table->longText('description')->nullable()->after('code');
            }

            if (!Schema::hasColumn('services', 'includes')) {
                $table->text('includes')->nullable()->after('description');
            }

            if (!Schema::hasColumn('services', 'price_type')) {
                $table->string('price_type')->nullable()->after('includes');
            }

            if (!Schema::hasColumn('services', 'slots')) {
                $table->json('slots')->nullable()->after('price');
            }

            if (!Schema::hasColumn('services', 'additional_services')) {
                $table->json('additional_services')->nullable()->after('slots');
            }

            if (!Schema::hasColumn('services', 'holidays')) {
                $table->json('holidays')->nullable()->after('additional_services');
            }

            if (!Schema::hasColumn('services', 'branch_ids')) {
                $table->json('branch_ids')->nullable()->after('holidays');
            }

            if (!Schema::hasColumn('services', 'gallery_image')) {
                $table->string('gallery_image')->nullable()->after('branch_ids');
            }

            if (!Schema::hasColumn('services', 'video_url')) {
                $table->string('video_url')->nullable()->after('gallery_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $columns = [
                'description',
                'includes',
                'price_type',
                'slots',
                'additional_services',
                'holidays',
                'branch_ids',
                'gallery_image',
                'video_url',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('services', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

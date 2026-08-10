<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'product_type')) {
            $updates = ['product_type' => 'all'];

            if (Schema::hasColumn('coupons', 'product_ids')) {
                $updates['product_ids'] = null;
            }

            DB::table('coupons')
                ->where('product_type', 'subcategory')
                ->update($updates);

            $this->setCouponProductTypeEnum(['all', 'service', 'category']);
        }

        if (Schema::hasTable('services') && Schema::hasColumn('services', 'sub_category')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('sub_category');
            });
        }

        if (Schema::hasTable('provider_staffs') && Schema::hasColumn('provider_staffs', 'sub_category_id')) {
            Schema::table('provider_staffs', function (Blueprint $table) {
                $table->dropColumn('sub_category_id');
            });
        }

        Schema::dropIfExists('service_sub_categories');
    }

    public function down(): void
    {
        if (Schema::hasTable('services') && ! Schema::hasColumn('services', 'sub_category')) {
            Schema::table('services', function (Blueprint $table) {
                $table->string('sub_category')->nullable()->after('category');
            });
        }

        if (Schema::hasTable('provider_staffs') && ! Schema::hasColumn('provider_staffs', 'sub_category_id')) {
            Schema::table('provider_staffs', function (Blueprint $table) {
                $table->unsignedBigInteger('sub_category_id')->nullable()->after('category_id');
            });
        }

        if (! Schema::hasTable('service_sub_categories') && Schema::hasTable('service_categories')) {
            Schema::create('service_sub_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_category_id')->constrained('service_categories')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('image')->nullable();
                $table->string('icon')->nullable();
                $table->text('description')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->boolean('is_featured')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'product_type')) {
            $this->setCouponProductTypeEnum(['all', 'service', 'category', 'subcategory']);
        }
    }

    private function setCouponProductTypeEnum(array $values): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $allowedValues = implode(', ', array_map(fn (string $value) => "'{$value}'", $values));

        DB::statement("ALTER TABLE coupons MODIFY product_type ENUM({$allowedValues}) NOT NULL DEFAULT 'all'");
    }
};

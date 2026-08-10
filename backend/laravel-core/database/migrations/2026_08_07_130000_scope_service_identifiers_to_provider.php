<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Product codes and slugs identify a service inside one provider's
            // catalogue. Different providers may legitimately use the same
            // SKU/code or service name.
            $table->dropUnique('services_code_unique');
            $table->dropUnique('services_slug_unique');

            $table->unique(['provider_id', 'code'], 'services_provider_code_unique');
            $table->unique(['provider_id', 'slug'], 'services_provider_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique('services_provider_code_unique');
            $table->dropUnique('services_provider_slug_unique');

            $table->unique('code', 'services_code_unique');
            $table->unique('slug', 'services_slug_unique');
        });
    }
};

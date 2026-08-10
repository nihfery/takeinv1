<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_branches', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_branches', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('city_id');
            }

            if (! Schema::hasColumn('provider_branches', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('provider_branches', function (Blueprint $table) {
            if (Schema::hasColumn('provider_branches', 'longitude')) {
                $table->dropColumn('longitude');
            }

            if (Schema::hasColumn('provider_branches', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};

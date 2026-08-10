<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE provider_branches MODIFY country_id VARCHAR(255) NULL');
        DB::statement('ALTER TABLE provider_branches MODIFY state_id VARCHAR(255) NULL');
        DB::statement('ALTER TABLE provider_branches MODIFY city_id VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE provider_branches MODIFY country_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE provider_branches MODIFY state_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE provider_branches MODIFY city_id BIGINT UNSIGNED NULL');
    }
};
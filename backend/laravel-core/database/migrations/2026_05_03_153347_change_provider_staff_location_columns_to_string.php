<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE provider_staffs
            MODIFY country_id VARCHAR(255) NULL,
            MODIFY state_id VARCHAR(255) NULL,
            MODIFY city_id VARCHAR(255) NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE provider_staffs
            SET country_id = NULL
            WHERE country_id IS NOT NULL AND country_id REGEXP '[^0-9]'
        ");

        DB::statement("
            UPDATE provider_staffs
            SET state_id = NULL
            WHERE state_id IS NOT NULL AND state_id REGEXP '[^0-9]'
        ");

        DB::statement("
            UPDATE provider_staffs
            SET city_id = NULL
            WHERE city_id IS NOT NULL AND city_id REGEXP '[^0-9]'
        ");

        DB::statement("
            ALTER TABLE provider_staffs
            MODIFY country_id BIGINT UNSIGNED NULL,
            MODIFY state_id BIGINT UNSIGNED NULL,
            MODIFY city_id BIGINT UNSIGNED NULL
        ");
    }
};
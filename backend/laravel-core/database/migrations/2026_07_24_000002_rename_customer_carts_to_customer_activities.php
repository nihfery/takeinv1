<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_carts') && ! Schema::hasTable('customer_activities')) {
            Schema::rename('customer_carts', 'customer_activities');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_activities') && ! Schema::hasTable('customer_carts')) {
            Schema::rename('customer_activities', 'customer_carts');
        }
    }
};

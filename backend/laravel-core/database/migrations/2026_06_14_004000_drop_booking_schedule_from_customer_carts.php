<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_carts', function (Blueprint $table) {
            if (Schema::hasColumn('customer_carts', 'booking_time')) {
                $table->dropColumn('booking_time');
            }

            if (Schema::hasColumn('customer_carts', 'booking_date')) {
                $table->dropColumn('booking_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_carts', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_carts', 'booking_date')) {
                $table->date('booking_date')->nullable()->after('total_duration');
            }

            if (! Schema::hasColumn('customer_carts', 'booking_time')) {
                $table->time('booking_time')->nullable()->after('booking_date');
            }
        });
    }
};

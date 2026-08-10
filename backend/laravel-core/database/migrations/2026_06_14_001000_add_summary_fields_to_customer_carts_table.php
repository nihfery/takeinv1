<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_carts', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_carts', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('customer_id');
            }

            if (! Schema::hasColumn('customer_carts', 'salon_name')) {
                $table->string('salon_name')->nullable()->after('branch_id');
            }

            if (! Schema::hasColumn('customer_carts', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->nullable()->after('salon_name');
            }

            if (! Schema::hasColumn('customer_carts', 'total_duration')) {
                $table->unsignedInteger('total_duration')->nullable()->after('total_amount');
            }

            if (! Schema::hasColumn('customer_carts', 'booking_date')) {
                $table->date('booking_date')->nullable()->after('total_duration');
            }

            if (! Schema::hasColumn('customer_carts', 'booking_time')) {
                $table->time('booking_time')->nullable()->after('booking_date');
            }

            if (! Schema::hasColumn('customer_carts', 'current_step')) {
                $table->unsignedTinyInteger('current_step')->default(1)->after('booking_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_carts', function (Blueprint $table) {
            foreach ([
                'current_step',
                'booking_time',
                'booking_date',
                'total_duration',
                'total_amount',
                'salon_name',
                'branch_id',
            ] as $column) {
                if (Schema::hasColumn('customer_carts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

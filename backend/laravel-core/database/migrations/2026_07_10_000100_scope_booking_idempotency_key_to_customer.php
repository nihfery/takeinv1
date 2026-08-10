<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'idempotency_key')) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $indexes = collect(DB::select('SHOW INDEX FROM bookings'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            if (in_array('bookings_idempotency_key_unique', $indexes, true)) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropUnique('bookings_idempotency_key_unique');
                });
            }
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'customer_id')) {
                $table->unique(['customer_id', 'idempotency_key'], 'bookings_customer_idempotency_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'idempotency_key')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_customer_idempotency_unique');
            $table->unique('idempotency_key', 'bookings_idempotency_key_unique');
        });
    }
};

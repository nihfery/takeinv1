<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'held_at')) {
                $table->dateTime('held_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('bookings', 'hold_expires_at')) {
                $table->dateTime('hold_expires_at')->nullable()->after('held_at');
            }

            if (Schema::hasColumn('bookings', 'staff_id')) {
                $table->index(['staff_id', 'booking_date', 'hold_expires_at'], 'bookings_staff_date_hold_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'staff_id')) {
                $table->dropIndex('bookings_staff_date_hold_index');
            }

            foreach (['hold_expires_at', 'held_at'] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

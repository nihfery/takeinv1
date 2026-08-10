<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'branch_id')) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('service_id')
                    ->constrained('provider_branches')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('bookings', 'booking_time')) {
                $table->time('booking_time')->nullable()->after('booking_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }

            if (Schema::hasColumn('bookings', 'booking_time')) {
                $table->dropColumn('booking_time');
            }
        });
    }
};

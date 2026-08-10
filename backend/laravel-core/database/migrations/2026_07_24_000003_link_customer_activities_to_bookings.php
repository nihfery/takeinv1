<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_activities', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->unique()->after('customer_id')->constrained('bookings')->cascadeOnDelete();
            $table->dropUnique('customer_carts_customer_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_activities', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropUnique(['booking_id']);
            $table->dropColumn('booking_id');
            $table->unique(['customer_id', 'branch_id'], 'customer_carts_customer_branch_unique');
        });
    }
};

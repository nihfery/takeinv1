<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'participant_count')) {
                $table->unsignedTinyInteger('participant_count')->default(1)->after('customer_phone');
            }
        });

        if (! Schema::hasTable('booking_participants')) {
            Schema::create('booking_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->unsignedTinyInteger('position');
                $table->boolean('is_primary')->default(false);
                $table->string('name');
                $table->string('phone', 30)->nullable();
                $table->string('email')->nullable();
                $table->timestamps();

                $table->unique(['booking_id', 'position']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_participants');

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'participant_count')) {
                $table->dropColumn('participant_count');
            }
        });
    }
};

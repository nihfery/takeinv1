<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_participants', function (Blueprint $table) {
            $table->foreignId('provider_staff_id')->nullable()->after('email')->constrained('provider_staffs')->nullOnDelete();
            $table->date('booking_date')->nullable()->after('provider_staff_id');
            $table->time('start_time')->nullable()->after('booking_date');
            $table->time('estimated_end_time')->nullable()->after('start_time');
            $table->unsignedInteger('total_duration')->default(0)->after('estimated_end_time');
            $table->decimal('total_price', 12, 2)->default(0)->after('total_duration');
        });

        Schema::create('booking_participant_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_participant_id')->constrained('booking_participants')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('estimated_duration')->default(30);
            $table->timestamps();

            $table->unique(['booking_participant_id', 'service_id'], 'participant_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_participant_services');

        Schema::table('booking_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_staff_id');
            $table->dropColumn([
                'booking_date',
                'start_time',
                'estimated_end_time',
                'total_duration',
                'total_price',
            ]);
        });
    }
};

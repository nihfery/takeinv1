<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_skills')) {
            Schema::create('staff_skills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('provider_staffs')->cascadeOnDelete();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['staff_id', 'service_id']);
            });
        }

        if (! Schema::hasTable('staff_schedules')) {
            Schema::create('staff_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('provider_staffs')->cascadeOnDelete();
                $table->string('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('is_available')->default(true);
                $table->timestamps();

                $table->index(['staff_id', 'day_of_week']);
            });
        }

        if (! Schema::hasTable('booking_services')) {
            Schema::create('booking_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->decimal('price', 12, 2)->default(0);
                $table->unsignedInteger('estimated_duration')->default(30);
                $table->timestamps();

                $table->unique(['booking_id', 'service_id']);
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->enum('payment_type', ['dp', 'full_payment', 'pay_at_salon']);
                $table->decimal('amount', 12, 2)->default(0);
                $table->enum('status', ['unpaid', 'pending', 'paid', 'failed', 'refunded'])->default('unpaid');
                $table->string('payment_method')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('provider_branches')->cascadeOnDelete();
                $table->foreignId('staff_id')->nullable()->constrained('provider_staffs')->nullOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->unique('booking_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('booking_services');
        Schema::dropIfExists('staff_schedules');
        Schema::dropIfExists('staff_skills');
    }
};

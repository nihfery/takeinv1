<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('category')
                    ->constrained('service_categories')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('services', 'minimum_duration')) {
                $table->unsignedInteger('minimum_duration')->default(0)->after('price');
            }

            if (! Schema::hasColumn('services', 'estimated_duration')) {
                $table->unsignedInteger('estimated_duration')->default(30)->after('minimum_duration');
            }

            if (! Schema::hasColumn('services', 'maximum_duration')) {
                $table->unsignedInteger('maximum_duration')->default(60)->after('estimated_duration');
            }

            if (! Schema::hasColumn('services', 'is_queue_enabled')) {
                $table->boolean('is_queue_enabled')->default(true)->after('maximum_duration');
            }

            if (! Schema::hasColumn('services', 'is_scheduled_enabled')) {
                $table->boolean('is_scheduled_enabled')->default(true)->after('is_queue_enabled');
            }

            if (! Schema::hasColumn('services', 'requires_dp')) {
                $table->boolean('requires_dp')->default(false)->after('is_scheduled_enabled');
            }

            if (! Schema::hasColumn('services', 'dp_amount')) {
                $table->decimal('dp_amount', 12, 2)->nullable()->after('requires_dp');
            }

            if (! Schema::hasColumn('services', 'payment_policy')) {
                $table->text('payment_policy')->nullable()->after('dp_amount');
            }
        });

        Schema::table('provider_staffs', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_staffs', 'rating')) {
                $table->decimal('rating', 3, 2)->nullable()->after('role');
            }

            if (! Schema::hasColumn('provider_staffs', 'current_status')) {
                $table->enum('current_status', ['available', 'busy', 'offline'])
                    ->default('available')
                    ->after('rating');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'staff_id')) {
                $table->foreignId('staff_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('provider_staffs')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('bookings', 'booking_type')) {
                $table->enum('booking_type', ['scheduled', 'queue', 'walk_in'])
                    ->default('scheduled')
                    ->after('staff_id');
            }

            if (! Schema::hasColumn('bookings', 'start_time')) {
                $table->time('start_time')->nullable()->after('booking_time');
            }

            if (! Schema::hasColumn('bookings', 'estimated_end_time')) {
                $table->time('estimated_end_time')->nullable()->after('start_time');
            }

            if (! Schema::hasColumn('bookings', 'actual_start_time')) {
                $table->dateTime('actual_start_time')->nullable()->after('estimated_end_time');
            }

            if (! Schema::hasColumn('bookings', 'actual_end_time')) {
                $table->dateTime('actual_end_time')->nullable()->after('actual_start_time');
            }

            if (! Schema::hasColumn('bookings', 'total_duration')) {
                $table->unsignedInteger('total_duration')->default(0)->after('actual_end_time');
            }

            if (! Schema::hasColumn('bookings', 'total_price')) {
                $table->decimal('total_price', 12, 2)->default(0)->after('total_duration');
            }

            if (! Schema::hasColumn('bookings', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'pending', 'paid', 'failed', 'refunded'])
                    ->default('unpaid')
                    ->after('total_price');
            }

            if (! Schema::hasColumn('bookings', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('bookings', 'customer_phone')) {
                $table->string('customer_phone')->nullable()->after('customer_name');
            }

            if (! Schema::hasColumn('bookings', 'notes')) {
                $table->text('notes')->nullable()->after('customer_phone');
            }

            if (! Schema::hasColumn('bookings', 'queue_number')) {
                $table->unsignedInteger('queue_number')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('bookings', 'checked_in_at')) {
                $table->dateTime('checked_in_at')->nullable()->after('queue_number');
            }

            if (! Schema::hasColumn('bookings', 'completed_at')) {
                $table->dateTime('completed_at')->nullable()->after('checked_in_at');
            }
        });

        $this->relaxLegacyBookingColumns();
        $this->expandBookingStatusEnum();
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach ([
                'completed_at',
                'checked_in_at',
                'queue_number',
                'notes',
                'customer_phone',
                'customer_name',
                'payment_status',
                'total_price',
                'total_duration',
                'actual_end_time',
                'actual_start_time',
                'estimated_end_time',
                'start_time',
                'booking_type',
            ] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('bookings', 'staff_id')) {
                $table->dropConstrainedForeignId('staff_id');
            }
        });

        Schema::table('provider_staffs', function (Blueprint $table) {
            foreach (['current_status', 'rating'] as $column) {
                if (Schema::hasColumn('provider_staffs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('services', function (Blueprint $table) {
            foreach ([
                'payment_policy',
                'dp_amount',
                'requires_dp',
                'is_scheduled_enabled',
                'is_queue_enabled',
                'maximum_duration',
                'estimated_duration',
                'minimum_duration',
            ] as $column) {
                if (Schema::hasColumn('services', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('services', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });
    }

    private function relaxLegacyBookingColumns(): void
    {
        if (! Schema::hasTable('bookings') || ! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (Schema::hasColumn('bookings', 'booking_date')) {
            DB::statement('ALTER TABLE bookings MODIFY booking_date DATE NULL');
        }

        if (Schema::hasColumn('bookings', 'customer_id')) {
            DB::statement('ALTER TABLE bookings MODIFY customer_id BIGINT UNSIGNED NULL');
        }

        if (Schema::hasColumn('bookings', 'service_id')) {
            DB::statement('ALTER TABLE bookings MODIFY service_id BIGINT UNSIGNED NULL');
        }
    }

    private function expandBookingStatusEnum(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'status')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $statuses = [
            'open',
            'pending',
            'inprogress',
            'completed',
            'order_completed',
            'refund_completed',
            'provider_cancelled',
            'customer_cancelled',
            'rescheduled',
            'pending_payment',
            'confirmed',
            'waiting',
            'checked_in',
            'in_progress',
            'cancelled',
            'no_show',
        ];

        $allowed = implode(', ', array_map(fn (string $status) => "'{$status}'", $statuses));

        DB::statement("ALTER TABLE bookings MODIFY status ENUM({$allowed}) NOT NULL DEFAULT 'open'");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $bookingStatuses = [
        'open',
        'pending',
        'pending_hold',
        'expired_hold',
        'payment_expired',
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

    private array $legacyBookingStatuses = [
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

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'expired_at')) {
                $table->dateTime('expired_at')->nullable()->after('hold_expires_at');
            }

            if (! Schema::hasColumn('bookings', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('expired_at');
            }
        });

        $this->modifyBookingStatusEnum($this->bookingStatuses);

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'idempotency_key')) {
                $table->unique('idempotency_key', 'bookings_idempotency_key_unique');
            }

            if (Schema::hasColumn('bookings', 'staff_id')) {
                $table->index(['staff_id', 'booking_date', 'status'], 'bookings_staff_date_status_idx');
            }

            if (Schema::hasColumn('bookings', 'branch_id')) {
                $table->index(['branch_id', 'booking_date', 'status'], 'bookings_branch_date_status_idx');
            }

            if (Schema::hasColumn('bookings', 'hold_expires_at')) {
                $table->index(['status', 'hold_expires_at'], 'bookings_status_hold_expiry_idx');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            DB::table('bookings')->where('status', 'pending_hold')->update(['status' => 'pending']);
            DB::table('bookings')->where('status', 'expired_hold')->update(['status' => 'cancelled']);
            DB::table('bookings')->where('status', 'payment_expired')->update(['status' => 'cancelled']);
        }

        Schema::table('bookings', function (Blueprint $table) {
            foreach ([
                'bookings_status_hold_expiry_idx',
                'bookings_branch_date_status_idx',
                'bookings_staff_date_status_idx',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable) {
                    //
                }
            }

            try {
                $table->dropUnique('bookings_idempotency_key_unique');
            } catch (Throwable) {
                //
            }
        });

        $this->modifyBookingStatusEnum($this->legacyBookingStatuses);

        Schema::table('bookings', function (Blueprint $table) {
            foreach (['idempotency_key', 'expired_at'] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function modifyBookingStatusEnum(array $statuses): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'status')) {
            return;
        }

        $allowed = implode(', ', array_map(fn (string $status) => "'{$status}'", $statuses));

        DB::statement("ALTER TABLE bookings MODIFY status ENUM({$allowed}) NOT NULL DEFAULT 'open'");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $paymentStatuses = ['unpaid', 'pending', 'paid', 'failed', 'refunded', 'expired'];

    private array $legacyPaymentStatuses = ['unpaid', 'pending', 'paid', 'failed', 'refunded'];

    public function up(): void
    {
        $this->modifyPaymentStatusEnums($this->paymentStatuses);
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'status')) {
            DB::table('payments')->where('status', 'expired')->update(['status' => 'failed']);
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'payment_status')) {
            DB::table('bookings')->where('payment_status', 'expired')->update(['payment_status' => 'failed']);
        }

        $this->modifyPaymentStatusEnums($this->legacyPaymentStatuses);
    }

    private function modifyPaymentStatusEnums(array $statuses): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $allowed = implode(', ', array_map(fn (string $status) => "'{$status}'", $statuses));

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'status')) {
            DB::statement("ALTER TABLE payments MODIFY status ENUM({$allowed}) NOT NULL DEFAULT 'unpaid'");
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'payment_status')) {
            DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM({$allowed}) NOT NULL DEFAULT 'unpaid'");
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateway_transactions')) {
            Schema::create('payment_gateway_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->unique()->constrained('payments')->cascadeOnDelete();
                $table->string('gateway', 40)->default('midtrans');
                $table->string('payment_channel', 60)->nullable();
                $table->string('provider_order_id')->nullable()->unique();
                $table->string('provider_transaction_id')->nullable()->index();
                $table->string('provider_status', 60)->nullable();
                $table->string('fraud_status', 60)->nullable();
                $table->string('payment_code_label')->nullable();
                $table->string('payment_code')->nullable();
                $table->string('biller_code')->nullable();
                $table->text('qr_url')->nullable();
                $table->text('deeplink_url')->nullable();
                $table->dateTime('expires_at')->nullable()->index();
                $table->json('raw_response')->nullable();
                $table->json('raw_notification')->nullable();
                $table->timestamps();

                $table->index(
                    ['gateway', 'provider_status', 'expires_at'],
                    'payment_gateway_status_expiry_index'
                );
            });
        }

        if (Schema::hasColumn('payments', 'payment_channel')) {
            DB::statement(<<<'SQL'
            INSERT IGNORE INTO payment_gateway_transactions (
                payment_id,
                gateway,
                payment_channel,
                provider_order_id,
                provider_transaction_id,
                provider_status,
                fraud_status,
                payment_code_label,
                payment_code,
                biller_code,
                qr_url,
                deeplink_url,
                expires_at,
                raw_response,
                raw_notification,
                created_at,
                updated_at
            )
            SELECT
                id,
                'midtrans',
                payment_channel,
                midtrans_order_id,
                midtrans_transaction_id,
                midtrans_transaction_status,
                fraud_status,
                payment_code_label,
                payment_code,
                biller_code,
                qr_url,
                deeplink_url,
                expiry_time,
                raw_response,
                raw_notification,
                created_at,
                updated_at
            FROM payments
            WHERE payment_type <> 'pay_at_salon'
               OR payment_channel IS NOT NULL
               OR midtrans_order_id IS NOT NULL
               OR midtrans_transaction_id IS NOT NULL
               OR expiry_time IS NOT NULL
        SQL);
        }
        DB::table('payment_gateway_transactions')
            ->join('payments', 'payments.id', '=', 'payment_gateway_transactions.payment_id')
            ->where('payments.status', 'paid')
            ->update([
                'payment_gateway_transactions.provider_status' => DB::raw(
                    "COALESCE(payment_gateway_transactions.provider_status, 'settlement')"
                ),
                'payment_gateway_transactions.expires_at' => null,
            ]);

        DB::table('payments')
            ->where('payment_type', 'pay_at_salon')
            ->whereNull('payment_method')
            ->update(['payment_method' => 'pay_at_salon']);

        if (Schema::hasColumn('bookings', 'amount')) {
            DB::statement(<<<'SQL'
            UPDATE bookings
            SET total_price = amount
            WHERE total_price = 0 AND amount > 0
        SQL);
        }

        if (Schema::hasColumn('bookings', 'booking_time')) {
            DB::statement(<<<'SQL'
            UPDATE bookings
            SET start_time = booking_time
            WHERE start_time IS NULL AND booking_time IS NOT NULL
        SQL);
        }

        if (Schema::hasColumn('bookings', 'service_id')) {
            DB::statement(<<<'SQL'
            INSERT INTO booking_services (
                booking_id,
                service_id,
                price,
                estimated_duration,
                created_at,
                updated_at
            )
            SELECT
                bookings.id,
                bookings.service_id,
                COALESCE(services.price, 0),
                COALESCE(NULLIF(services.estimated_duration, 0), 30),
                bookings.created_at,
                bookings.updated_at
            FROM bookings
            INNER JOIN services ON services.id = bookings.service_id
            LEFT JOIN booking_services ON booking_services.booking_id = bookings.id
                AND booking_services.service_id = bookings.service_id
            WHERE bookings.service_id IS NOT NULL
              AND booking_services.id IS NULL
        SQL);
        }

        if (Schema::hasColumn('customer_activities', 'payload')) {
            DB::statement(<<<'SQL'
            INSERT IGNORE INTO customer_activities (
                customer_id,
                booking_id,
                branch_id,
                salon_name,
                total_amount,
                total_duration,
                current_step,
                payload,
                saved_at,
                expires_at,
                created_at,
                updated_at
            )
            SELECT
                bookings.customer_id,
                bookings.id,
                bookings.branch_id,
                provider_branches.branch_name,
                bookings.total_price,
                bookings.total_duration,
                4,
                JSON_OBJECT(),
                COALESCE(bookings.updated_at, NOW()),
                NULL,
                COALESCE(bookings.created_at, NOW()),
                COALESCE(bookings.updated_at, NOW())
            FROM bookings
            LEFT JOIN provider_branches ON provider_branches.id = bookings.branch_id
            WHERE bookings.customer_id IS NOT NULL
              AND bookings.status IN (
                'pending_payment',
                'confirmed',
                'waiting',
                'checked_in',
                'in_progress',
                'inprogress',
                'rescheduled',
                'completed',
                'order_completed',
                'cancelled',
                'customer_cancelled',
                'provider_cancelled',
                'no_show',
                'payment_expired',
                'refund_completed'
              )
        SQL);
        }

        if (! $this->indexExists('payments', 'payments_booking_id_unique')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unique('booking_id', 'payments_booking_id_unique');
            });
        }

        if ($this->foreignKeyExists('bookings', 'bookings_service_id_foreign')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign('bookings_service_id_foreign');
            });
        }
        $bookingColumns = collect([
                'booking_time',
                'service_id',
                'amount',
                'payment_status',
            ])
            ->filter(fn (string $column) => Schema::hasColumn('bookings', $column))
            ->all();
        if ($bookingColumns !== []) {
            Schema::table('bookings', function (Blueprint $table) use ($bookingColumns) {
                $table->dropColumn($bookingColumns);
            });
        }

        if ($this->indexExists('payments', 'payments_midtrans_order_id_unique')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropUnique('payments_midtrans_order_id_unique');
            });
        }
        $paymentColumns = collect([
                'payment_channel',
                'midtrans_order_id',
                'midtrans_transaction_id',
                'midtrans_transaction_status',
                'fraud_status',
                'payment_code_label',
                'payment_code',
                'biller_code',
                'qr_url',
                'deeplink_url',
                'expiry_time',
                'raw_response',
                'raw_notification',
            ])
            ->filter(fn (string $column) => Schema::hasColumn('payments', $column))
            ->all();
        if ($paymentColumns !== []) {
            Schema::table('payments', function (Blueprint $table) use ($paymentColumns) {
                $table->dropColumn($paymentColumns);
            });
        }

        if (! $this->indexExists('customer_activities', 'customer_activities_customer_created_index')) {
            Schema::table('customer_activities', function (Blueprint $table) {
                $table->index(['customer_id', 'created_at'], 'customer_activities_customer_created_index');
            });
        }
        if ($this->foreignKeyExists('customer_activities', 'customer_carts_branch_id_foreign')) {
            Schema::table('customer_activities', function (Blueprint $table) {
                $table->dropForeign('customer_carts_branch_id_foreign');
            });
        }
        if ($this->indexExists('customer_activities', 'customer_carts_branch_id_foreign')) {
            Schema::table('customer_activities', function (Blueprint $table) {
                $table->dropIndex('customer_carts_branch_id_foreign');
            });
        }
        if ($this->indexExists('customer_activities', 'customer_carts_customer_id_index')) {
            Schema::table('customer_activities', function (Blueprint $table) {
                $table->dropIndex('customer_carts_customer_id_index');
            });
        }
        $activityColumns = collect([
                'branch_id',
                'salon_name',
                'total_amount',
                'total_duration',
                'current_step',
                'payload',
                'saved_at',
                'expires_at',
            ])
            ->filter(fn (string $column) => Schema::hasColumn('customer_activities', $column))
            ->all();
        if ($activityColumns !== []) {
            Schema::table('customer_activities', function (Blueprint $table) use ($activityColumns) {
                $table->dropColumn($activityColumns);
            });
        }
        // Legacy cart drafts do not have a booking_id. Preserve those rows on
        // existing production databases; all new activity rows are still
        // written with a booking through CustomerActivity::recordBooking().

        Schema::table('branch_reviews', function (Blueprint $table) {
            $table->dropForeign('branch_reviews_customer_id_foreign');
            $table->dropForeign('branch_reviews_provider_id_foreign');
            $table->dropForeign('branch_reviews_branch_id_foreign');
            $table->dropIndex('branch_reviews_customer_id_foreign');
            $table->dropIndex('branch_reviews_provider_id_foreign');
            $table->dropIndex('branch_reviews_branch_id_created_at_index');
            $table->dropColumn(['customer_id', 'provider_id', 'branch_id']);
        });

        Schema::table('staff_reviews', function (Blueprint $table) {
            $table->dropForeign('staff_reviews_customer_id_foreign');
            $table->dropForeign('staff_reviews_provider_id_foreign');
            $table->dropForeign('staff_reviews_branch_id_foreign');
            $table->dropIndex('staff_reviews_customer_id_foreign');
            $table->dropIndex('staff_reviews_provider_id_foreign');
            $table->dropIndex('staff_reviews_branch_id_foreign');
            $table->dropColumn(['customer_id', 'provider_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->time('booking_time')->nullable()->after('booking_date');
            $table->foreignId('service_id')->nullable()->after('customer_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0)->after('booking_type');
            $table->enum('payment_status', ['unpaid', 'pending', 'paid', 'failed', 'refunded', 'expired'])
                ->default('unpaid')
                ->after('total_price');
        });

        DB::statement(<<<'SQL'
            UPDATE bookings
            LEFT JOIN (
                SELECT booking_id, MIN(service_id) AS service_id
                FROM booking_services
                GROUP BY booking_id
            ) services ON services.booking_id = bookings.id
            LEFT JOIN payments ON payments.booking_id = bookings.id
            SET bookings.booking_time = bookings.start_time,
                bookings.service_id = services.service_id,
                bookings.amount = bookings.total_price,
                bookings.payment_status = COALESCE(payments.status, 'unpaid')
        SQL);

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_channel')->nullable();
            $table->string('midtrans_order_id')->nullable()->unique();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('midtrans_transaction_status')->nullable();
            $table->string('fraud_status')->nullable();
            $table->string('payment_code_label')->nullable();
            $table->string('payment_code')->nullable();
            $table->string('biller_code')->nullable();
            $table->text('qr_url')->nullable();
            $table->text('deeplink_url')->nullable();
            $table->dateTime('expiry_time')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('raw_notification')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE payments
            INNER JOIN payment_gateway_transactions ON payment_gateway_transactions.payment_id = payments.id
            SET payments.payment_channel = payment_gateway_transactions.payment_channel,
                payments.midtrans_order_id = payment_gateway_transactions.provider_order_id,
                payments.midtrans_transaction_id = payment_gateway_transactions.provider_transaction_id,
                payments.midtrans_transaction_status = payment_gateway_transactions.provider_status,
                payments.fraud_status = payment_gateway_transactions.fraud_status,
                payments.payment_code_label = payment_gateway_transactions.payment_code_label,
                payments.payment_code = payment_gateway_transactions.payment_code,
                payments.biller_code = payment_gateway_transactions.biller_code,
                payments.qr_url = payment_gateway_transactions.qr_url,
                payments.deeplink_url = payment_gateway_transactions.deeplink_url,
                payments.expiry_time = payment_gateway_transactions.expires_at,
                payments.raw_response = payment_gateway_transactions.raw_response,
                payments.raw_notification = payment_gateway_transactions.raw_notification
        SQL);

        Schema::table('customer_activities', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained('provider_branches')->nullOnDelete();
            $table->string('salon_name')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->unsignedInteger('total_duration')->nullable();
            $table->unsignedTinyInteger('current_step')->default(4);
            $table->json('payload')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        });

        Schema::table('branch_reviews', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('provider_branches')->cascadeOnDelete();
            $table->index(['branch_id', 'created_at'], 'branch_reviews_branch_id_created_at_index');
        });

        Schema::table('staff_reviews', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('provider_branches')->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE branch_reviews
            INNER JOIN bookings ON bookings.id = branch_reviews.booking_id
            SET branch_reviews.customer_id = bookings.customer_id,
                branch_reviews.provider_id = bookings.provider_id,
                branch_reviews.branch_id = bookings.branch_id
        SQL);
        DB::statement(<<<'SQL'
            UPDATE staff_reviews
            INNER JOIN bookings ON bookings.id = staff_reviews.booking_id
            SET staff_reviews.customer_id = bookings.customer_id,
                staff_reviews.provider_id = bookings.provider_id,
                staff_reviews.branch_id = bookings.branch_id
        SQL);

        Schema::table('customer_activities', function (Blueprint $table) {
            $table->dropIndex('customer_activities_customer_created_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_booking_id_unique');
        });

        Schema::dropIfExists('payment_gateway_transactions');
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $foreignKey)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};

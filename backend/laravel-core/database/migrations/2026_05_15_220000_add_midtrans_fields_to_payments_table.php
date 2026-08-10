<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'payment_channel')) {
                $table->string('payment_channel')->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('payments', 'midtrans_order_id')) {
                $table->string('midtrans_order_id')->nullable()->after('payment_channel')->unique();
            }

            if (! Schema::hasColumn('payments', 'midtrans_transaction_id')) {
                $table->string('midtrans_transaction_id')->nullable()->after('midtrans_order_id');
            }

            if (! Schema::hasColumn('payments', 'midtrans_transaction_status')) {
                $table->string('midtrans_transaction_status')->nullable()->after('midtrans_transaction_id');
            }

            if (! Schema::hasColumn('payments', 'fraud_status')) {
                $table->string('fraud_status')->nullable()->after('midtrans_transaction_status');
            }

            if (! Schema::hasColumn('payments', 'payment_code_label')) {
                $table->string('payment_code_label')->nullable()->after('fraud_status');
            }

            if (! Schema::hasColumn('payments', 'payment_code')) {
                $table->string('payment_code')->nullable()->after('payment_code_label');
            }

            if (! Schema::hasColumn('payments', 'biller_code')) {
                $table->string('biller_code')->nullable()->after('payment_code');
            }

            if (! Schema::hasColumn('payments', 'qr_url')) {
                $table->text('qr_url')->nullable()->after('biller_code');
            }

            if (! Schema::hasColumn('payments', 'deeplink_url')) {
                $table->text('deeplink_url')->nullable()->after('qr_url');
            }

            if (! Schema::hasColumn('payments', 'expiry_time')) {
                $table->dateTime('expiry_time')->nullable()->after('deeplink_url');
            }

            if (! Schema::hasColumn('payments', 'raw_response')) {
                $table->json('raw_response')->nullable()->after('expiry_time');
            }

            if (! Schema::hasColumn('payments', 'raw_notification')) {
                $table->json('raw_notification')->nullable()->after('raw_response');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            foreach ([
                'raw_notification',
                'raw_response',
                'expiry_time',
                'deeplink_url',
                'qr_url',
                'biller_code',
                'payment_code',
                'payment_code_label',
                'fraud_status',
                'midtrans_transaction_status',
                'midtrans_transaction_id',
                'midtrans_order_id',
                'payment_channel',
            ] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

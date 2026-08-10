<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_subscriptions', function (Blueprint $table) {
            $table->string('payment_channel', 60)->nullable()->after('midtrans_transaction_id');
            $table->string('midtrans_transaction_status', 60)->nullable()->after('payment_channel');
            $table->string('fraud_status', 60)->nullable()->after('midtrans_transaction_status');
            $table->string('payment_code_label')->nullable()->after('fraud_status');
            $table->string('payment_code')->nullable()->after('payment_code_label');
            $table->string('biller_code')->nullable()->after('payment_code');
            $table->text('qr_url')->nullable()->after('biller_code');
            $table->text('deeplink_url')->nullable()->after('qr_url');
            $table->dateTime('gateway_expires_at')->nullable()->after('deeplink_url');
            $table->json('gateway_response')->nullable()->after('gateway_expires_at');
            $table->json('gateway_notification')->nullable()->after('gateway_response');
            $table->dateTime('superseded_at')->nullable()->after('gateway_notification');
            $table->dateTime('late_settlement_at')->nullable()->after('superseded_at');

            $table->index(
                ['payment_status', 'gateway_expires_at'],
                'provider_subscription_gateway_status_expiry_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('provider_subscriptions', function (Blueprint $table) {
            $table->dropIndex('provider_subscription_gateway_status_expiry_index');
            $table->dropColumn([
                'payment_channel',
                'midtrans_transaction_status',
                'fraud_status',
                'payment_code_label',
                'payment_code',
                'biller_code',
                'qr_url',
                'deeplink_url',
                'gateway_expires_at',
                'gateway_response',
                'gateway_notification',
                'superseded_at',
                'late_settlement_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provider_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            
            // Snapshot data
            $table->string('plan_name');
            $table->decimal('price', 15, 2);
            $table->string('currency')->default('IDR');
            $table->integer('duration_days');
            $table->integer('max_branches')->default(1);
            
            // Split statuses
            $table->string('payment_status')->default('pending'); // pending, paid, failed, expired, canceled
            $table->string('subscription_status')->default('inactive'); // inactive, active, expired
            
            // Time ranges
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            
            // Payment tracking
            $table->string('midtrans_order_id')->unique()->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_subscriptions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('payload');
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_carts');
    }
};

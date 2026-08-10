<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_staffs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('image')->nullable();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('username')->nullable();

            $table->string('country_code', 20)->nullable();
            $table->string('phone_number')->nullable();

            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();

            $table->text('address')->nullable();

            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();

            $table->string('postal_code')->nullable();
            $table->text('bio')->nullable();

            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();

            $table->string('role')->default('staff');
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();

            $table->unique(['provider_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_staffs');
    }
};

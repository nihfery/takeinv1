<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id')->index();

            $table->string('branch_name');
            $table->string('email')->nullable();
            $table->string('phone_code')->default('+1');
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable();

            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();

            $table->string('zip_code')->nullable();
            $table->time('working_start_hour')->nullable();
            $table->time('working_end_hour')->nullable();

            $table->json('working_days')->nullable();
            $table->json('holidays')->nullable();

            $table->string('image')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_branches');
    }
};
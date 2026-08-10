<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable()->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('last_admin_read_at')->nullable();
            $table->timestamp('last_provider_read_at')->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->timestamps();

            $table->unique('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_threads');
    }
};

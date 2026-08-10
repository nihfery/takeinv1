<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            Schema::create('provider_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('phone_number')->nullable();
                $table->string('category')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });

            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('provider_profiles', 'phone_number')) {
                $table->string('phone_number')
                    ->nullable()
                    ->after('user_id');
            }

            if (! Schema::hasColumn('provider_profiles', 'category')) {
                $table->string('category')
                    ->nullable()
                    ->after('phone_number');
            }

            if (! Schema::hasColumn('provider_profiles', 'status')) {
                $table->enum('status', ['active', 'inactive'])
                    ->default('active')
                    ->after('category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('provider_profiles', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('provider_profiles', 'category')) {
                $table->dropColumn('category');
            }

            if (Schema::hasColumn('provider_profiles', 'phone_number')) {
                $table->dropColumn('phone_number');
            }

            if (Schema::hasColumn('provider_profiles', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
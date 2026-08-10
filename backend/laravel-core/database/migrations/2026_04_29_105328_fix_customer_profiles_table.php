<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_profiles')) {
            Schema::create('customer_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('phone_number')->nullable();
                $table->string('gender')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('avatar')->nullable();
                $table->text('address_line_1')->nullable();
                $table->text('address_line_2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('country')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });

            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_profiles', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('customer_profiles', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('customer_profiles', 'gender')) {
                $table->string('gender')->nullable()->after('phone_number');
            }

            if (! Schema::hasColumn('customer_profiles', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('gender');
            }

            if (! Schema::hasColumn('customer_profiles', 'avatar')) {
                $table->string('avatar')->nullable()->after('date_of_birth');
            }

            if (! Schema::hasColumn('customer_profiles', 'address_line_1')) {
                $table->text('address_line_1')->nullable()->after('avatar');
            }

            if (! Schema::hasColumn('customer_profiles', 'address_line_2')) {
                $table->text('address_line_2')->nullable()->after('address_line_1');
            }

            if (! Schema::hasColumn('customer_profiles', 'city')) {
                $table->string('city')->nullable()->after('address_line_2');
            }

            if (! Schema::hasColumn('customer_profiles', 'state')) {
                $table->string('state')->nullable()->after('city');
            }

            if (! Schema::hasColumn('customer_profiles', 'country')) {
                $table->string('country')->nullable()->after('state');
            }

            if (! Schema::hasColumn('customer_profiles', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('customer_profiles', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('customer_profiles', 'country')) {
                $table->dropColumn('country');
            }

            if (Schema::hasColumn('customer_profiles', 'state')) {
                $table->dropColumn('state');
            }

            if (Schema::hasColumn('customer_profiles', 'city')) {
                $table->dropColumn('city');
            }

            if (Schema::hasColumn('customer_profiles', 'address_line_2')) {
                $table->dropColumn('address_line_2');
            }

            if (Schema::hasColumn('customer_profiles', 'address_line_1')) {
                $table->dropColumn('address_line_1');
            }

            if (Schema::hasColumn('customer_profiles', 'avatar')) {
                $table->dropColumn('avatar');
            }

            if (Schema::hasColumn('customer_profiles', 'date_of_birth')) {
                $table->dropColumn('date_of_birth');
            }

            if (Schema::hasColumn('customer_profiles', 'gender')) {
                $table->dropColumn('gender');
            }

            if (Schema::hasColumn('customer_profiles', 'phone_number')) {
                $table->dropColumn('phone_number');
            }

            if (Schema::hasColumn('customer_profiles', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
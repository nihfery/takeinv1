<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('image')->nullable();
                $table->string('icon')->nullable();
                $table->text('description')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->boolean('is_featured')->default(true);
                $table->timestamps();
            });

            return;
        }

        Schema::table('service_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('service_categories', 'image')) {
                $table->string('image')->nullable()->after('slug');
            }

            if (! Schema::hasColumn('service_categories', 'icon')) {
                $table->string('icon')->nullable()->after('image');
            }

            if (! Schema::hasColumn('service_categories', 'description')) {
                $table->text('description')->nullable()->after('icon');
            }

            if (! Schema::hasColumn('service_categories', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('description');
            }

            if (! Schema::hasColumn('service_categories', 'is_featured')) {
                $table->boolean('is_featured')->default(true)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
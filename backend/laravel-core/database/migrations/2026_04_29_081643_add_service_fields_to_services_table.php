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
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'category')) {
                $table->string('category')->nullable()->after('slug');
            }

            if (! Schema::hasColumn('services', 'code')) {
                $table->string('code')->nullable()->unique()->after('category');
            }

            if (! Schema::hasColumn('services', 'verify_status')) {
                $table->enum('verify_status', ['verified', 'pending', 'rejected'])
                    ->default('verified')
                    ->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'verify_status')) {
                $table->dropColumn('verify_status');
            }

            if (Schema::hasColumn('services', 'code')) {
                $table->dropColumn('code');
            }

            if (Schema::hasColumn('services', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};

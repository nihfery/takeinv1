<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('provider_profiles') && !Schema::hasColumn('provider_profiles', 'image')) {
            Schema::table('provider_profiles', function (Blueprint $table) {
                $table->string('image')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('provider_profiles') && Schema::hasColumn('provider_profiles', 'image')) {
            Schema::table('provider_profiles', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
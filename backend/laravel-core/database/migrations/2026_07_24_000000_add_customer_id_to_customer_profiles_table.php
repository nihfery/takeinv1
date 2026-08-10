<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_profiles', 'customer_id')) {
            Schema::table('customer_profiles', function (Blueprint $table) {
                $table->string('customer_id', 32)->nullable()->unique();
            });
        }

        // Give existing profiles the same stable, customer-only identifier as
        // profiles created after this migration.
        foreach (DB::table('customer_profiles')->select('id')->whereNull('customer_id')->orderBy('id')->cursor() as $profile) {
            DB::table('customer_profiles')
                ->where('id', $profile->id)
                ->update(['customer_id' => sprintf('CUST-%06d', $profile->id)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_profiles', 'customer_id')) {
            Schema::table('customer_profiles', function (Blueprint $table) {
                $table->dropUnique(['customer_id']);
                $table->dropColumn('customer_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_profiles', 'document_status')) {
                $table->string('document_status')->default('pending')->after('status');
            }

            if (!Schema::hasColumn('provider_profiles', 'ktp_image')) {
                $table->string('ktp_image')->nullable()->after('document_status');
            }

            if (!Schema::hasColumn('provider_profiles', 'business_image')) {
                $table->string('business_image')->nullable()->after('ktp_image');
            }

            if (!Schema::hasColumn('provider_profiles', 'document_note')) {
                $table->text('document_note')->nullable()->after('business_image');
            }
        });

        DB::table('provider_profiles')
            ->whereNull('document_status')
            ->update([
                'document_status' => 'pending',
            ]);
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            foreach (['document_status', 'ktp_image', 'business_image', 'document_note'] as $column) {
                if (Schema::hasColumn('provider_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
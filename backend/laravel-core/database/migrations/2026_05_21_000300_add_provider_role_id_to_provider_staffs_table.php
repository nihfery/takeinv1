<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_staffs', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_staffs', 'provider_role_id')) {
                $table->foreignId('provider_role_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('provider_roles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('provider_staffs', function (Blueprint $table) {
            if (Schema::hasColumn('provider_staffs', 'provider_role_id')) {
                $table->dropConstrainedForeignId('provider_role_id');
            }
        });
    }
};

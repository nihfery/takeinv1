<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'provider_id')) {
                $table->foreignId('provider_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'branch_id')) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('provider_id')
                    ->constrained('provider_branches')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'provider_role_id')) {
                $table->foreignId('provider_role_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('provider_roles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'provider_role_id')) {
                $table->dropConstrainedForeignId('provider_role_id');
            }

            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }

            if (Schema::hasColumn('users', 'provider_id')) {
                $table->dropConstrainedForeignId('provider_id');
            }
        });
    }
};

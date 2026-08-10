<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_carts', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_carts', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('saved_at');
            }
        });

        if (Schema::hasColumn('customer_carts', 'expires_at')) {
            DB::table('customer_carts')
                ->whereNull('expires_at')
                ->update(['expires_at' => DB::raw('DATE_ADD(COALESCE(saved_at, updated_at, NOW()), INTERVAL 7 DAY)')]);
        }

        if (Schema::hasColumn('customer_carts', 'branch_id')) {
            DB::table('customer_carts')
                ->leftJoin('provider_branches', 'customer_carts.branch_id', '=', 'provider_branches.id')
                ->whereNotNull('customer_carts.branch_id')
                ->whereNull('provider_branches.id')
                ->update(['customer_carts.branch_id' => null]);

            if (! $this->hasForeignKey('customer_carts', 'customer_carts_branch_id_foreign')) {
                Schema::table('customer_carts', function (Blueprint $table) {
                    $table->foreign('branch_id')
                        ->references('id')
                        ->on('provider_branches')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_carts', 'branch_id') && $this->hasForeignKey('customer_carts', 'customer_carts_branch_id_foreign')) {
            Schema::table('customer_carts', function (Blueprint $table) {
                $table->dropForeign('customer_carts_branch_id_foreign');
            });
        }

        Schema::table('customer_carts', function (Blueprint $table) {
            if (Schema::hasColumn('customer_carts', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_carts')) {
            return;
        }

        $this->deleteDuplicateBranchCarts();

        if (Schema::hasColumn('customer_carts', 'customer_id') && ! $this->hasIndex('customer_carts', 'customer_carts_customer_id_index')) {
            Schema::table('customer_carts', function (Blueprint $table) {
                $table->index('customer_id', 'customer_carts_customer_id_index');
            });
        }

        if ($this->hasIndex('customer_carts', 'customer_carts_customer_id_unique')) {
            Schema::table('customer_carts', function (Blueprint $table) {
                $table->dropUnique('customer_carts_customer_id_unique');
            });
        }

        if (
            Schema::hasColumn('customer_carts', 'customer_id')
            && Schema::hasColumn('customer_carts', 'branch_id')
            && ! $this->hasIndex('customer_carts', 'customer_carts_customer_branch_unique')
        ) {
            Schema::table('customer_carts', function (Blueprint $table) {
                $table->unique(['customer_id', 'branch_id'], 'customer_carts_customer_branch_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_carts')) {
            return;
        }

        if ($this->hasIndex('customer_carts', 'customer_carts_customer_branch_unique')) {
            Schema::table('customer_carts', function (Blueprint $table) {
                $table->dropUnique('customer_carts_customer_branch_unique');
            });
        }

        $this->deleteDuplicateCustomerCarts();

        if (Schema::hasColumn('customer_carts', 'customer_id') && ! $this->hasIndex('customer_carts', 'customer_carts_customer_id_unique')) {
            Schema::table('customer_carts', function (Blueprint $table) {
                $table->unique('customer_id', 'customer_carts_customer_id_unique');
            });
        }
    }

    private function deleteDuplicateBranchCarts(): void
    {
        DB::statement(<<<'SQL'
            DELETE duplicate_carts FROM customer_carts duplicate_carts
            INNER JOIN customer_carts kept_carts
                ON kept_carts.customer_id = duplicate_carts.customer_id
                AND kept_carts.branch_id = duplicate_carts.branch_id
                AND kept_carts.id > duplicate_carts.id
            WHERE duplicate_carts.branch_id IS NOT NULL
        SQL);
    }

    private function deleteDuplicateCustomerCarts(): void
    {
        DB::statement(<<<'SQL'
            DELETE duplicate_carts FROM customer_carts duplicate_carts
            INNER JOIN customer_carts kept_carts
                ON kept_carts.customer_id = duplicate_carts.customer_id
                AND kept_carts.id > duplicate_carts.id
        SQL);
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('branch_reviews', 'images')) {
            Schema::table('branch_reviews', function (Blueprint $table) {
                $table->json('images')->nullable()->after('comment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('branch_reviews', 'images')) {
            Schema::table('branch_reviews', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        }
    }
};

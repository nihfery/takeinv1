<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_branches', function (Blueprint $table) {
            // Gallery of up to 5 branch photos. The single `image` column is kept as
            // the cover photo (first of this list) for backward compatibility.
            $table->json('images')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('provider_branches', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};

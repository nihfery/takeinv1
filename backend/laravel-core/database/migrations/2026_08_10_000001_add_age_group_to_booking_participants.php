<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_participants', function (Blueprint $table) {
            $table->string('age_group', 20)->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('booking_participants', function (Blueprint $table) {
            $table->dropColumn('age_group');
        });
    }
};

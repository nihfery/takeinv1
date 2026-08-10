<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_profiles', 'religion')) {
                $table->string('religion')->nullable()->after('date_of_birth');
            }

            if (! Schema::hasColumn('customer_profiles', 'allergies')) {
                $table->text('allergies')->nullable()->after('religion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('customer_profiles', 'allergies')) {
                $table->dropColumn('allergies');
            }

            if (Schema::hasColumn('customer_profiles', 'religion')) {
                $table->dropColumn('religion');
            }
        });
    }
};

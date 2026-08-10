<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_role_menu_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_role_id')->constrained('provider_roles')->cascadeOnDelete();
            $table->string('menu_key');

            $table->unique(['provider_role_id', 'menu_key'], 'provider_role_menu_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_role_menu_permissions');
    }
};

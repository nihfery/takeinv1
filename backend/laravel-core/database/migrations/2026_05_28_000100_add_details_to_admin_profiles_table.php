<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_profiles')) {
            Schema::create('admin_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
                $table->string('phone_number')->nullable();
                $table->string('position')->nullable();
                $table->string('avatar')->nullable();
                $table->text('bio')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('admin_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_profiles', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->unique()
                    ->after('id')
                    ->constrained('users')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('admin_profiles', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('admin_profiles', 'position')) {
                $table->string('position')->nullable()->after('phone_number');
            }

            if (! Schema::hasColumn('admin_profiles', 'avatar')) {
                $table->string('avatar')->nullable()->after('position');
            }

            if (! Schema::hasColumn('admin_profiles', 'bio')) {
                $table->text('bio')->nullable()->after('avatar');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_profiles')) {
            return;
        }

        Schema::table('admin_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('admin_profiles', 'bio')) {
                $table->dropColumn('bio');
            }

            if (Schema::hasColumn('admin_profiles', 'avatar')) {
                $table->dropColumn('avatar');
            }

            if (Schema::hasColumn('admin_profiles', 'position')) {
                $table->dropColumn('position');
            }

            if (Schema::hasColumn('admin_profiles', 'phone_number')) {
                $table->dropColumn('phone_number');
            }

            if (Schema::hasColumn('admin_profiles', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};

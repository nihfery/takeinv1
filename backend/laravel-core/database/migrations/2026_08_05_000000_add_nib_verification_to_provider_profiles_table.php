<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'nib_number')) {
                $table->string('nib_number', 50)->nullable()->after('ktp_image');
            }

            if (! Schema::hasColumn('provider_profiles', 'nib_document')) {
                $table->string('nib_document')->nullable()->after('nib_number');
            }

            if (! Schema::hasColumn('provider_profiles', 'document_submitted_at')) {
                $table->timestamp('document_submitted_at')->nullable()->after('document_note');
            }

            if (! Schema::hasColumn('provider_profiles', 'document_verified_at')) {
                $table->timestamp('document_verified_at')->nullable()->after('document_submitted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            foreach (['nib_number', 'nib_document', 'document_submitted_at', 'document_verified_at'] as $column) {
                if (Schema::hasColumn('provider_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

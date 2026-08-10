<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'audit_logs_correlation_id_index';

    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (! Schema::hasColumn('audit_logs', 'correlation_id')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('correlation_id', 64)->nullable();
            });
        }

        if (! Schema::hasIndex('audit_logs', self::INDEX)) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->index('correlation_id', self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (Schema::hasIndex('audit_logs', self::INDEX)) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }

        if (Schema::hasColumn('audit_logs', 'correlation_id')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropColumn('correlation_id');
            });
        }
    }
};

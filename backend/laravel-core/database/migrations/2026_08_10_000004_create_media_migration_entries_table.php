<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_migration_entries', function (Blueprint $table): void {
            $table->id();
            $table->char('migration_key', 64)->unique();
            $table->string('scope', 50);
            $table->string('subject_type', 50);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_field', 64);
            $table->string('source_disk', 100);
            $table->text('source_path');
            $table->char('source_fingerprint', 64);
            $table->string('target_disk', 100);
            $table->text('target_path');
            $table->char('target_fingerprint', 64);
            $table->char('source_checksum', 64)->nullable();
            $table->char('target_checksum', 64)->nullable();
            $table->string('archive_disk', 100)->nullable();
            $table->text('archive_path')->nullable();
            $table->char('archive_fingerprint', 64)->nullable();
            $table->char('archive_checksum', 64)->nullable();
            $table->string('status', 32)->default('planned');
            $table->timestamp('copy_started_at')->nullable();
            $table->timestamp('copied_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('cutover_at')->nullable();
            $table->timestamp('archive_verified_at')->nullable();
            $table->timestamp('source_retired_at')->nullable();
            $table->timestamp('source_restored_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->unsignedInteger('retirement_count')->default(0);
            $table->unsignedInteger('rollback_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['scope', 'status']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('source_fingerprint');
            $table->index('target_fingerprint');
            $table->index('archive_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_migration_entries');
    }
};

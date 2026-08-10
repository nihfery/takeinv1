<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_threads')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM chat_threads'))
            ->pluck('Key_name')
            ->unique();

        if ($indexes->contains('chat_threads_provider_id_unique')) {
            DB::statement('ALTER TABLE chat_threads DROP INDEX chat_threads_provider_id_unique');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_threads')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM chat_threads'))
            ->pluck('Key_name')
            ->unique();

        if (! $indexes->contains('chat_threads_provider_id_unique')) {
            DB::statement('ALTER TABLE chat_threads ADD UNIQUE chat_threads_provider_id_unique (provider_id)');
        }
    }
};

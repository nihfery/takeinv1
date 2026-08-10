<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('chat_threads', function (Blueprint $table) {
                $table->dropUnique('chat_threads_provider_id_unique');
            });
        } catch (\Throwable) {
            // Some databases may already have this index removed.
        }

        Schema::table('chat_threads', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_threads', 'provider_user_id')) {
                $table->foreignId('provider_user_id')
                    ->nullable()
                    ->after('provider_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('chat_threads', 'branch_user_id')) {
                $table->foreignId('branch_user_id')
                    ->nullable()
                    ->after('provider_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('chat_threads', 'conversation_type')) {
                $table->string('conversation_type', 40)
                    ->default('provider_admin')
                    ->index()
                    ->after('branch_user_id');
            }

            if (! Schema::hasColumn('chat_threads', 'last_branch_read_at')) {
                $table->timestamp('last_branch_read_at')
                    ->nullable()
                    ->after('last_provider_read_at');
            }

            if (! Schema::hasColumn('chat_threads', 'opened_by_user_id')) {
                $table->foreignId('opened_by_user_id')
                    ->nullable()
                    ->after('ticket_reviewed_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('chat_threads', 'closed_by_user_id')) {
                $table->foreignId('closed_by_user_id')
                    ->nullable()
                    ->after('opened_by_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('chat_threads', 'closed_at')) {
                $table->timestamp('closed_at')
                    ->nullable()
                    ->index()
                    ->after('closed_by_user_id');
            }

            $table->index(['provider_id', 'conversation_type', 'ticket_status'], 'chat_threads_provider_type_status_index');
        });

        DB::table('chat_threads')
            ->whereNull('conversation_type')
            ->update(['conversation_type' => 'provider_admin']);

        DB::table('chat_threads')
            ->whereNull('provider_user_id')
            ->update(['provider_user_id' => DB::raw('provider_id')]);

        DB::table('chat_threads')
            ->whereNull('opened_by_user_id')
            ->update(['opened_by_user_id' => DB::raw('provider_user_id')]);

        DB::table('chat_threads')
            ->where('ticket_status', 'closed')
            ->whereNull('closed_at')
            ->update(['closed_at' => DB::raw('COALESCE(ticket_reviewed_at, updated_at)')]);
    }

    public function down(): void
    {
        try {
            Schema::table('chat_threads', function (Blueprint $table) {
                $table->dropIndex('chat_threads_provider_type_status_index');
            });
        } catch (\Throwable) {
            // Ignore when rolling back a partially-applied migration.
        }

        Schema::table('chat_threads', function (Blueprint $table) {
            if (Schema::hasColumn('chat_threads', 'closed_by_user_id')) {
                $table->dropConstrainedForeignId('closed_by_user_id');
            }

            if (Schema::hasColumn('chat_threads', 'opened_by_user_id')) {
                $table->dropConstrainedForeignId('opened_by_user_id');
            }

            if (Schema::hasColumn('chat_threads', 'branch_user_id')) {
                $table->dropConstrainedForeignId('branch_user_id');
            }

            if (Schema::hasColumn('chat_threads', 'provider_user_id')) {
                $table->dropConstrainedForeignId('provider_user_id');
            }

            foreach ([
                'closed_at',
                'last_branch_read_at',
                'conversation_type',
            ] as $column) {
                if (Schema::hasColumn('chat_threads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        try {
            Schema::table('chat_threads', function (Blueprint $table) {
                $table->unique('provider_id');
            });
        } catch (\Throwable) {
            // The unique index may still exist on some databases.
        }
    }
};

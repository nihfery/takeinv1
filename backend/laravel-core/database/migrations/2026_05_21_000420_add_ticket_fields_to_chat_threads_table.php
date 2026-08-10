<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_threads', 'ticket_status')) {
                $table->string('ticket_status', 30)
                    ->default('none')
                    ->index()
                    ->after('status');
            }

            if (! Schema::hasColumn('chat_threads', 'ticket_subject')) {
                $table->string('ticket_subject', 160)
                    ->nullable()
                    ->after('ticket_status');
            }

            if (! Schema::hasColumn('chat_threads', 'ticket_body')) {
                $table->text('ticket_body')
                    ->nullable()
                    ->after('ticket_subject');
            }

            if (! Schema::hasColumn('chat_threads', 'ticket_rejection_reason')) {
                $table->text('ticket_rejection_reason')
                    ->nullable()
                    ->after('ticket_body');
            }

            if (! Schema::hasColumn('chat_threads', 'ticket_requested_at')) {
                $table->timestamp('ticket_requested_at')
                    ->nullable()
                    ->index()
                    ->after('ticket_rejection_reason');
            }

            if (! Schema::hasColumn('chat_threads', 'ticket_reviewed_at')) {
                $table->timestamp('ticket_reviewed_at')
                    ->nullable()
                    ->after('ticket_requested_at');
            }

            if (! Schema::hasColumn('chat_threads', 'ticket_reviewed_by')) {
                $table->foreignId('ticket_reviewed_by')
                    ->nullable()
                    ->after('ticket_reviewed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        DB::table('chat_threads')
            ->whereNotNull('last_message_id')
            ->where(function ($query) {
                $query->whereNull('ticket_status')
                    ->orWhere('ticket_status', 'none');
            })
            ->update([
                'ticket_status' => 'approved',
                'ticket_subject' => 'Percakapan sebelumnya',
                'ticket_requested_at' => DB::raw('COALESCE(last_message_at, updated_at, created_at)'),
                'ticket_reviewed_at' => DB::raw('COALESCE(last_message_at, updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('chat_threads', function (Blueprint $table) {
            if (Schema::hasColumn('chat_threads', 'ticket_reviewed_by')) {
                $table->dropConstrainedForeignId('ticket_reviewed_by');
            }

            foreach ([
                'ticket_reviewed_at',
                'ticket_requested_at',
                'ticket_rejection_reason',
                'ticket_body',
                'ticket_subject',
                'ticket_status',
            ] as $column) {
                if (Schema::hasColumn('chat_threads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->index(['chat_thread_id', 'sender_role', 'created_at'], 'chat_messages_thread_role_created_index');
            });
        }

        if (Schema::hasTable('chat_threads')) {
            Schema::table('chat_threads', function (Blueprint $table) {
                $table->index(['provider_id', 'conversation_type', 'ticket_status', 'closed_at', 'last_message_at'], 'chat_threads_provider_chat_list_index');
            });
        }

        if (Schema::hasTable('app_notifications')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->index(['user_id', 'type', 'id'], 'app_notifications_user_type_id_index');
                $table->index(['user_id', 'type', 'read_at'], 'app_notifications_user_type_read_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('app_notifications')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->dropIndex('app_notifications_user_type_read_index');
                $table->dropIndex('app_notifications_user_type_id_index');
            });
        }

        if (Schema::hasTable('chat_threads')) {
            Schema::table('chat_threads', function (Blueprint $table) {
                $table->dropIndex('chat_threads_provider_chat_list_index');
            });
        }

        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropIndex('chat_messages_thread_role_created_index');
            });
        }
    }
};

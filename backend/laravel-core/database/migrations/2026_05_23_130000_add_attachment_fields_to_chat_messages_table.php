<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('body');
            }

            if (! Schema::hasColumn('chat_messages', 'attachment_name')) {
                $table->string('attachment_name')->nullable()->after('attachment_path');
            }

            if (! Schema::hasColumn('chat_messages', 'attachment_mime')) {
                $table->string('attachment_mime', 120)->nullable()->after('attachment_name');
            }

            if (! Schema::hasColumn('chat_messages', 'attachment_size')) {
                $table->unsignedInteger('attachment_size')->nullable()->after('attachment_mime');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            foreach (['attachment_size', 'attachment_mime', 'attachment_name', 'attachment_path'] as $column) {
                if (Schema::hasColumn('chat_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

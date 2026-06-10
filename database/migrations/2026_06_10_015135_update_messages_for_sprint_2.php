<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('sender');
            $table->enum('sender_type', ['customer', 'admin', 'ai', 'system'])->default('customer')->after('conversation_id');
            $table->enum('message_type', ['text', 'image', 'file', 'audio'])->default('text')->after('message');
            $table->json('metadata')->nullable()->after('message_type');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->enum('sender', ['customer', 'admin', 'ai']);
            $table->dropColumn(['sender_type', 'message_type', 'metadata']);
        });
    }
};

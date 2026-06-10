<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: SQLite doesn't strictly enforce string enums dynamically changing, 
        // but we'll alter conversations to add new fields.
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('tags')->nullable();
            $table->string('sentiment')->nullable();
        });

        // Alter messages
        Schema::table('messages', function (Blueprint $table) {
            $table->string('intent')->nullable();
        });

        // New tables
        Schema::create('conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id'); // Mocked for demo
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('quick_replies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('ai_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('feedback'); // 'good' or 'bad'
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_url');
            $table->string('file_type');
            $table->integer('file_size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ai_feedbacks');
        Schema::dropIfExists('quick_replies');
        Schema::dropIfExists('conversation_notes');
        
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('intent');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['tags', 'sentiment']);
        });
    }
};

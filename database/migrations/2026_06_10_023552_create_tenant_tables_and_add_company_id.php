<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_users')->default(1);
            $table->integer('max_channels')->default(1);
            $table->integer('max_ai_requests')->default(100);
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('name');
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });

        $tables = ['users', 'channels', 'customers', 'conversations', 'knowledge_bases', 'quick_replies'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tables = ['quick_replies', 'knowledge_bases', 'conversations', 'customers', 'channels', 'users'];
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
        
        Schema::dropIfExists('companies');
        Schema::dropIfExists('plans');
    }
};

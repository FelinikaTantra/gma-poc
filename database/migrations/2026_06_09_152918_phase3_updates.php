<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->integer('unread_count')->default(0);
            $table->string('status')->default('open');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['username', 'first_name', 'last_name']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['unread_count', 'status']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('app_id')->nullable();
            $table->string('secret')->nullable();
            $table->text('token')->nullable();
            $table->string('webhook_url')->nullable();
            $table->enum('status', ['Connected', 'Disconnected'])->default('Disconnected');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};

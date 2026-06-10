<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('channel'); // shopee, tokopedia, tiktok
            $table->string('external_store_id')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('external_product_id')->nullable();
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->timestamps();
        });

        Schema::create('product_compatibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('vehicle_brand')->nullable(); // e.g., Honda, Yamaha
            $table->string('vehicle_model')->nullable(); // e.g., Vario 125, NMAX
            $table->string('vehicle_year')->nullable(); // e.g., 2022-2025
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('blacklist_status')->default(false)->after('name');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete()->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('blacklist_status');
        });

        Schema::dropIfExists('product_compatibility');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
    }
};

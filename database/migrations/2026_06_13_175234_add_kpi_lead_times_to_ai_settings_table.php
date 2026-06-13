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
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->integer('kpi_lead_time_ai')->default(15)->after('temperature');
            $table->integer('kpi_lead_time_manual')->default(300)->after('kpi_lead_time_ai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['kpi_lead_time_ai', 'kpi_lead_time_manual']);
        });
    }
};

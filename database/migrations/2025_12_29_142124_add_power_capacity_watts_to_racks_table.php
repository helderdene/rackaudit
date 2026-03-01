<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add power_capacity_watts column to racks table for power capacity tracking.
 *
 * This field stores the maximum power capacity in watts for each rack,
 * enabling capacity planning and power utilization calculations.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->unsignedInteger('power_capacity_watts')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropColumn('power_capacity_watts');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add device_count column to capacity_snapshots table.
 *
 * Stores the total device count for each datacenter at snapshot time,
 * enabling device count trend analysis on the dashboard charts.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('capacity_snapshots', function (Blueprint $table) {
            $table->unsignedInteger('device_count')->nullable()->after('port_stats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capacity_snapshots', function (Blueprint $table) {
            $table->dropColumn('device_count');
        });
    }
};

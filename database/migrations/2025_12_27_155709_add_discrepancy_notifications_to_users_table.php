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
        Schema::table('users', function (Blueprint $table) {
            // Notification preference for discrepancy alerts
            // Options: 'all' (all discrepancies), 'threshold_only' (only when threshold exceeded), 'none'
            // Default: 'none' for operators, configurable per user
            $table->string('discrepancy_notifications', 20)->default('none')->after('last_active_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('discrepancy_notifications');
        });
    }
};

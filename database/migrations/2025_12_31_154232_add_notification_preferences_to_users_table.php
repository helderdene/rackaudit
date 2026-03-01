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
            // JSON column for per-category email notification preferences
            // Stores preferences like: {"audit_assignments": true, "finding_updates": false}
            // Default is null, which means all email notifications are enabled (opt-out model)
            // Keep existing discrepancy_notifications field for backward compatibility
            $table->json('notification_preferences')->nullable()->after('discrepancy_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};

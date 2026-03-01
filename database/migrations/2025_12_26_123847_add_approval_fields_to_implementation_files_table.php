<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add approval workflow fields to implementation_files table.
 *
 * This migration adds approval_status enum, approved_by foreign key,
 * and approved_at timestamp to support the implementation file approval workflow.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('implementation_files', function (Blueprint $table) {
            // Add approval_status enum field with values: "pending_approval", "approved"
            // Default to "pending_approval" for new files
            $table->enum('approval_status', ['pending_approval', 'approved'])
                ->default('pending_approval')
                ->after('version_number')
                ->comment('Approval status: pending_approval or approved');

            // Add approved_by foreign key to users table (nullable, nullOnDelete)
            $table->foreignId('approved_by')
                ->nullable()
                ->after('approval_status')
                ->constrained('users')
                ->nullOnDelete();

            // Add approved_at timestamp field (nullable)
            $table->timestamp('approved_at')
                ->nullable()
                ->after('approved_by');

            // Add index on approval_status for filtering performance
            $table->index('approval_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('implementation_files', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['approved_by']);

            // Drop the index
            $table->dropIndex(['approval_status']);

            // Drop the columns
            $table->dropColumn(['approval_status', 'approved_by', 'approved_at']);
        });
    }
};

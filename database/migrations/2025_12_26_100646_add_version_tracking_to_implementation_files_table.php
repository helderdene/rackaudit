<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add version tracking fields to implementation_files table.
 *
 * This migration adds version_group_id and version_number columns to enable
 * tracking of multiple versions of the same logical file. The replaced_at and
 * replaced_by columns are removed as they are superseded by version tracking.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('implementation_files', function (Blueprint $table) {
            // Add version tracking columns
            $table->unsignedBigInteger('version_group_id')
                ->nullable()
                ->after('uploaded_by')
                ->comment('Self-referential FK to first file in version chain');

            $table->unsignedInteger('version_number')
                ->default(1)
                ->after('version_group_id')
                ->comment('Sequential version number within the group');

            // Add foreign key constraint (self-referential)
            $table->foreign('version_group_id')
                ->references('id')
                ->on('implementation_files')
                ->nullOnDelete();

            // Add composite index for efficient version queries
            $table->index(['version_group_id', 'version_number']);

            // Remove replaced_at and replaced_by columns (superseded by version tracking)
            $table->dropForeign(['replaced_by']);
            $table->dropColumn(['replaced_at', 'replaced_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('implementation_files', function (Blueprint $table) {
            // Drop foreign key first (must be done before dropping the index it uses)
            $table->dropForeign(['version_group_id']);

            // Now drop the composite index
            $table->dropIndex(['version_group_id', 'version_number']);

            // Drop version tracking columns
            $table->dropColumn(['version_group_id', 'version_number']);

            // Restore replaced_at and replaced_by columns
            $table->timestamp('replaced_at')->nullable()->after('uploaded_by');
            $table->foreignId('replaced_by')
                ->nullable()
                ->after('replaced_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }
};

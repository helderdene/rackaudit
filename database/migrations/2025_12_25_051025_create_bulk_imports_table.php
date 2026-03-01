<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create bulk_imports table for tracking import job status and progress.
 *
 * BulkImport records track the status, progress, and results of bulk
 * import operations for datacenter infrastructure data (datacenters,
 * rooms, rows, racks, devices, and ports).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bulk_imports', function (Blueprint $table) {
            $table->id();

            // User who initiated the import
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Import metadata
            $table->string('entity_type');
            $table->string('file_name');
            $table->string('file_path');

            // Status tracking
            $table->string('status')->default('pending');

            // Progress tracking
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);

            // Error report path (if failures exist)
            $table->string('error_report_path')->nullable();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Indexes for filtering and queries
            $table->index('status');
            $table->index('entity_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_imports');
    }
};

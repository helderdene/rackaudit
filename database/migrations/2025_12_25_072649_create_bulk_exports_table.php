<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create bulk_exports table for tracking export job status and progress.
 *
 * BulkExport records track the status, progress, and results of bulk
 * export operations for datacenter infrastructure data (datacenters,
 * rooms, rows, racks, devices, and ports).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bulk_exports', function (Blueprint $table) {
            $table->id();

            // User who initiated the export
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Export metadata
            $table->string('entity_type');
            $table->string('format')->default('xlsx'); // csv or xlsx
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();

            // Status tracking
            $table->string('status')->default('pending');

            // Progress tracking
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);

            // Hierarchical filters applied to the export
            $table->json('filters')->nullable();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Indexes for filtering and queries
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_exports');
    }
};

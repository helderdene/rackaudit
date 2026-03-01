<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create dashboard_snapshots table for historical audit and activity metrics.
 *
 * Stores daily snapshots of audit findings, completion metrics, and activity
 * counts by entity type for dashboard trend analysis and historical reporting.
 * Each datacenter can have one snapshot per date.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datacenter_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');

            // Findings counts by severity
            $table->unsignedInteger('open_findings_count')->default(0);
            $table->unsignedInteger('critical_findings_count')->default(0);
            $table->unsignedInteger('high_findings_count')->default(0);
            $table->unsignedInteger('medium_findings_count')->default(0);
            $table->unsignedInteger('low_findings_count')->default(0);

            // Audit metrics
            $table->unsignedInteger('pending_audits_count')->default(0);
            $table->unsignedInteger('completed_audits_count')->default(0);

            // Activity metrics
            $table->unsignedInteger('activity_count')->default(0);
            $table->json('activity_by_entity')->nullable();

            $table->timestamps();

            // Unique constraint: one snapshot per datacenter per date
            $table->unique(['datacenter_id', 'snapshot_date']);

            // Indexes for efficient querying
            $table->index('snapshot_date');
            $table->index('datacenter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_snapshots');
    }
};

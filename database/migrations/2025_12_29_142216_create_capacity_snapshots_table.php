<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create capacity_snapshots table for historical capacity tracking.
 *
 * Stores periodic snapshots of datacenter capacity metrics for trend analysis
 * and historical reporting. Each datacenter can have one snapshot per date.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('capacity_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datacenter_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('rack_utilization_percent', 5, 2);
            $table->decimal('power_utilization_percent', 5, 2)->nullable();
            $table->unsignedInteger('total_u_space');
            $table->unsignedInteger('used_u_space');
            $table->unsignedInteger('total_power_capacity')->nullable();
            $table->unsignedInteger('total_power_consumption')->nullable();
            $table->json('port_stats');
            $table->timestamps();

            // Add unique constraint on datacenter_id and snapshot_date
            $table->unique(['datacenter_id', 'snapshot_date']);

            // Add indexes for efficient querying
            $table->index('snapshot_date');
            $table->index('datacenter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capacity_snapshots');
    }
};

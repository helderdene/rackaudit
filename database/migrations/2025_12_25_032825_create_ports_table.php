<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create ports table for device port management.
 *
 * Ports represent physical connection points on devices with type
 * classification, labeling, status tracking, and visual position
 * data for future port mapping capabilities.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ports', function (Blueprint $table) {
            $table->id();

            // Device relationship (cascade delete when device is deleted)
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            // Port identification
            $table->string('label');

            // Type classification
            $table->string('type');
            $table->string('subtype');

            // Status (defaults to 'available')
            $table->string('status')->default('available');

            // Direction for connection validation
            $table->string('direction');

            // Physical position fields (for modular devices and patch panels)
            $table->unsignedInteger('position_slot')->nullable();
            $table->unsignedInteger('position_row')->nullable();
            $table->unsignedInteger('position_column')->nullable();

            // Visual port mapping position data (percentage 0-100)
            $table->decimal('visual_x', 5, 2)->nullable();
            $table->decimal('visual_y', 5, 2)->nullable();
            $table->string('visual_face')->nullable();

            $table->timestamps();

            // Indexes for filtering and queries
            $table->index('device_id');
            $table->index('type');
            $table->index('status');

            // Unique constraint: label must be unique within same device
            $table->unique(['device_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ports');
    }
};

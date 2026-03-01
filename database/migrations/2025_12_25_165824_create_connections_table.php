<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create connections table for tracking port-to-port cable connections.
 *
 * Connections represent physical cables between two ports with cable
 * properties (type, length, color) and optional path notes. Supports
 * soft deletes for historical tracking.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();

            // Port relationships (cascade delete when port is deleted)
            $table->foreignId('source_port_id')
                ->constrained('ports')
                ->cascadeOnDelete();
            $table->foreignId('destination_port_id')
                ->constrained('ports')
                ->cascadeOnDelete();

            // Cable properties
            $table->string('cable_type');
            $table->decimal('cable_length', 8, 2)->nullable();
            $table->string('cable_color', 50)->nullable();

            // Additional documentation
            $table->text('path_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient lookups
            $table->index('source_port_id');
            $table->index('destination_port_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};

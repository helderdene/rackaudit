<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create expected_connections table for storing parsed connections from implementation files.
 *
 * Expected connections represent the intended port-to-port mappings extracted from
 * implementation specification documents (Excel/CSV files). They serve as the
 * authoritative reference for comparing against actual documented connections
 * during datacenter audits.
 *
 * Supports soft deletes for archiving previous versions when new implementation
 * file versions are uploaded.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expected_connections', function (Blueprint $table) {
            $table->id();

            // Link to source implementation file
            $table->foreignId('implementation_file_id')
                ->constrained('implementation_files')
                ->cascadeOnDelete();

            // Source device and port (nullable to allow for unmatched/unrecognized entries)
            $table->foreignId('source_device_id')
                ->nullable()
                ->constrained('devices')
                ->nullOnDelete();
            $table->foreignId('source_port_id')
                ->nullable()
                ->constrained('ports')
                ->nullOnDelete();

            // Destination device and port (nullable to allow for unmatched/unrecognized entries)
            $table->foreignId('dest_device_id')
                ->nullable()
                ->constrained('devices')
                ->nullOnDelete();
            $table->foreignId('dest_port_id')
                ->nullable()
                ->constrained('ports')
                ->nullOnDelete();

            // Cable properties (optional, from parsed file)
            $table->string('cable_type')->nullable();
            $table->decimal('cable_length', 8, 2)->nullable();

            // Row number from the source file for reference
            $table->unsignedInteger('row_number');

            // Review status: pending_review, confirmed, skipped
            $table->string('status')->default('pending_review');

            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient querying
            $table->index('implementation_file_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expected_connections');
    }
};

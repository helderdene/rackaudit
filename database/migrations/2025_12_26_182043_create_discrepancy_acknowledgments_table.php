<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create discrepancy_acknowledgments table for storing user acknowledgments of connection discrepancies.
 *
 * Discrepancy acknowledgments allow users to mark specific discrepancies as reviewed/deferred
 * during the connection comparison process. This enables tracking of which mismatches have been
 * reviewed and any notes about why they exist or when they will be resolved.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discrepancy_acknowledgments', function (Blueprint $table) {
            $table->id();

            // Reference to expected connection (nullable - for expected but missing, mismatched, or conflicting)
            $table->foreignId('expected_connection_id')
                ->nullable()
                ->constrained('expected_connections')
                ->cascadeOnDelete();

            // Reference to actual connection (nullable - for unexpected or mismatched connections)
            $table->foreignId('connection_id')
                ->nullable()
                ->constrained('connections')
                ->cascadeOnDelete();

            // Type of discrepancy being acknowledged
            $table->string('discrepancy_type');

            // User who acknowledged the discrepancy
            $table->foreignId('acknowledged_by')
                ->constrained('users')
                ->cascadeOnDelete();

            // When the discrepancy was acknowledged
            $table->timestamp('acknowledged_at');

            // Optional notes explaining the acknowledgment or resolution plan
            $table->text('notes')->nullable();

            $table->timestamps();

            // Unique constraint: only one acknowledgment per expected_connection + connection + discrepancy_type combination
            $table->unique(
                ['expected_connection_id', 'connection_id', 'discrepancy_type'],
                'unique_acknowledgment'
            );

            // Index for filtering by acknowledged_at
            $table->index('acknowledged_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discrepancy_acknowledgments');
    }
};

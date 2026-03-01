<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discrepancies', function (Blueprint $table) {
            $table->id();

            // Scope relationships
            $table->foreignId('datacenter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('implementation_file_id')->nullable()->constrained()->nullOnDelete();

            // Discrepancy classification
            $table->string('discrepancy_type');
            $table->string('status')->default('open');

            // Port and connection references
            $table->foreignId('source_port_id')->constrained('ports')->cascadeOnDelete();
            $table->foreignId('dest_port_id')->constrained('ports')->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expected_connection_id')->nullable()->constrained()->nullOnDelete();

            // Configuration comparison data (JSON)
            $table->json('expected_config')->nullable();
            $table->json('actual_config')->nullable();
            $table->json('mismatch_details')->nullable();

            // Descriptive fields
            $table->string('title');
            $table->text('description')->nullable();

            // Detection tracking
            $table->timestamp('detected_at');

            // Acknowledgment tracking
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();

            // Resolution tracking
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            // Audit workflow integration
            $table->foreignId('audit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // Indexes for common query patterns
            $table->index('datacenter_id');
            $table->index('status');
            $table->index('discrepancy_type');
            $table->index('detected_at');

            // Unique constraint for upsert logic: same port pair + type should not duplicate
            $table->unique(['source_port_id', 'dest_port_id', 'discrepancy_type'], 'discrepancies_port_pair_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discrepancies');
    }
};

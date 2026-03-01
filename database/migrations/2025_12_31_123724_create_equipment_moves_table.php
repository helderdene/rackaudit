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
        Schema::create('equipment_moves', function (Blueprint $table) {
            $table->id();

            // Device being moved
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');

            // Source location
            $table->foreignId('source_rack_id')->constrained('racks')->onDelete('cascade');
            $table->unsignedSmallInteger('source_start_u');
            $table->string('source_rack_face', 10);
            $table->string('source_width_type', 20);

            // Destination location
            $table->foreignId('destination_rack_id')->constrained('racks')->onDelete('cascade');
            $table->unsignedSmallInteger('destination_start_u');
            $table->string('destination_rack_face', 10);
            $table->string('destination_width_type', 20);

            // Workflow status
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'executed', 'cancelled'])
                ->default('pending_approval');

            // Connection snapshot captured before move
            $table->json('connections_snapshot')->nullable();

            // User who requested the move
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');

            // User who approved/rejected the move (nullable, uses nullOnDelete like ImplementationFile)
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Notes
            $table->text('operator_notes')->nullable();
            $table->text('approval_notes')->nullable();

            // Timestamps for workflow tracking
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('device_id');
            $table->index('status');
            $table->index('requested_by');
            $table->index('approved_by');
            $table->index('source_rack_id');
            $table->index('destination_rack_id');
            $table->index('requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_moves');
    }
};

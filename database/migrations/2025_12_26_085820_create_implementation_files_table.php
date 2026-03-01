<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create implementation_files table for storing implementation specification documents.
 *
 * This table stores metadata for uploaded implementation files (PDF, Excel, CSV,
 * Word, text) that serve as the authoritative source for expected connections
 * in datacenter audits.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('implementation_files', function (Blueprint $table) {
            $table->id();

            // Foreign key to datacenters table - cascade delete when datacenter is deleted
            $table->foreignId('datacenter_id')
                ->constrained('datacenters')
                ->cascadeOnDelete();

            // File identification
            $table->string('file_name');  // UUID-based stored filename
            $table->string('original_name');  // User's original filename
            $table->text('description')->nullable();  // Optional description (max 500 chars)

            // File storage and metadata
            $table->string('file_path');  // Full storage path
            $table->unsignedBigInteger('file_size');  // Size in bytes
            $table->string('mime_type');  // e.g., application/pdf

            // User who uploaded the file
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Replacement tracking
            $table->timestamp('replaced_at')->nullable();
            $table->foreignId('replaced_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Soft deletes for history preservation
            $table->softDeletes();

            $table->timestamps();

            // Indexes for query performance
            $table->index('datacenter_id');
            $table->index('uploaded_by');
            $table->index('created_at');
            $table->index('original_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('implementation_files');
    }
};

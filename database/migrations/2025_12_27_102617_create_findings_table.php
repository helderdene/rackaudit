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
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_connection_verification_id')
                ->nullable()
                ->constrained('audit_connection_verifications')
                ->nullOnDelete();
            $table->string('discrepancy_type'); // matched, missing, unexpected, mismatched, conflicting
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open, resolved
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('audit_id');
            $table->index('status');
            $table->index('discrepancy_type');
            $table->index('audit_connection_verification_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};

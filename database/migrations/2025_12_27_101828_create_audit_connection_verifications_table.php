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
        Schema::create('audit_connection_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expected_connection_id')->nullable()->constrained('expected_connections')->nullOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('discrepancy_type')->nullable(); // matched, missing, unexpected, mismatched, conflicting
            $table->string('comparison_status'); // matched, missing, unexpected, mismatched, conflicting
            $table->string('verification_status')->default('pending'); // pending, verified, discrepant
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('audit_id');
            $table->index('verification_status');
            $table->index('locked_by');
            $table->index('discrepancy_type');

            // Composite unique index to prevent duplicate verification entries
            $table->unique(
                ['audit_id', 'expected_connection_id', 'connection_id'],
                'acv_audit_expected_connection_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_connection_verifications');
    }
};

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
        Schema::create('audit_device_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();
            $table->string('verification_status')->default('pending'); // pending, verified, not_found, discrepant
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('audit_id');
            $table->index('device_id');
            $table->index('verification_status');
            $table->index('locked_by');

            // Composite index for progress queries
            $table->index(['audit_id', 'verification_status']);

            // Unique constraint to prevent duplicate verification entries for same device in an audit
            $table->unique(['audit_id', 'device_id'], 'adv_audit_device_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_device_verifications');
    }
};

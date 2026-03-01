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
        Schema::table('findings', function (Blueprint $table) {
            // Add severity column with default value of 'medium'
            $table->string('severity')->default('medium')->after('status');

            // Add assigned user foreign key (nullable)
            $table->foreignId('assigned_to')
                ->nullable()
                ->after('severity')
                ->constrained('users')
                ->nullOnDelete();

            // Add finding category foreign key (nullable)
            $table->foreignId('finding_category_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('finding_categories')
                ->nullOnDelete();

            // Add resolution notes text column (nullable)
            $table->text('resolution_notes')->nullable()->after('finding_category_id');

            // Add indexes for common queries
            $table->index('severity');
            $table->index('assigned_to');
            $table->index('finding_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['finding_category_id']);

            // Drop indexes
            $table->dropIndex(['severity']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['finding_category_id']);

            // Drop columns
            $table->dropColumn(['severity', 'assigned_to', 'finding_category_id', 'resolution_notes']);
        });
    }
};

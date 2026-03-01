<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create pdus table for Power Distribution Unit management.
 *
 * PDUs can be assigned at either room level (serving the entire room)
 * or row level (serving a specific row of racks).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pdus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->decimal('total_capacity_kw', 10, 2)->nullable();
            $table->integer('voltage')->nullable();
            $table->string('phase');
            $table->integer('circuit_count');
            $table->string('status');
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('row_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Add indexes for filtering and lookups
            $table->index('room_id');
            $table->index('row_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdus');
    }
};

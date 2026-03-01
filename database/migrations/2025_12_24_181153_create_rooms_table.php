<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create rooms table for datacenter room/zone management.
 *
 * Rooms represent physical spaces within a datacenter that can contain
 * rows of racks and PDUs for power distribution.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('square_footage', 10, 2)->nullable();
            $table->string('type');
            $table->foreignId('datacenter_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Add indexes for search and filtering
            $table->index('datacenter_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};

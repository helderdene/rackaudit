<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create device_types table for categorizing datacenter devices.
 *
 * Device types allow users to define custom categories for their equipment
 * (e.g., Server, Switch, Router) with default U-height values for rack placement.
 * Soft deletes preserve historical references when a device type is removed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('default_u_size')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // Unique index on name to prevent duplicate device types
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_types');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create devices table for datacenter device/asset management.
 *
 * Devices represent physical equipment that can be placed in racks.
 * Each device has a unique auto-generated asset tag, lifecycle status,
 * physical dimensions, and optional rack placement.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();

            // Required fields
            $table->string('name');
            $table->string('asset_tag')->unique();
            $table->string('lifecycle_status');
            $table->foreignId('device_type_id')->constrained()->restrictOnDelete();

            // Optional identification fields
            $table->string('serial_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();

            // Warranty and purchase information
            $table->date('purchase_date')->nullable();
            $table->date('warranty_start_date')->nullable();
            $table->date('warranty_end_date')->nullable();

            // Physical dimension fields
            $table->unsignedTinyInteger('u_height')->default(1);
            $table->string('depth')->default('standard');
            $table->string('width_type')->default('full');
            $table->string('rack_face')->default('front');

            // Rack placement (nullable for unplaced/inventory devices)
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('start_u')->nullable();

            // Flexible specifications (JSON key-value pairs)
            $table->json('specs')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for filtering and queries
            $table->index('lifecycle_status');
            $table->index('rack_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create racks table for rack management within rows.
 *
 * Racks are physical equipment containers within a row that hold
 * servers and other datacenter equipment. Each rack has a U-height
 * indicating the number of available rack units.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('position');
            $table->integer('u_height');
            $table->string('serial_number')->nullable();
            $table->string('status');
            $table->foreignId('row_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Add indexes for ordering and filtering
            $table->index('row_id');
            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('racks');
    }
};

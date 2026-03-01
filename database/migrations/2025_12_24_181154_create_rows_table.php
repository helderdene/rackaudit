<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create rows table for rack row management within rooms.
 *
 * Rows represent physical lanes within a room where racks are positioned,
 * with hot/cold aisle designation for proper cooling management.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('position');
            $table->string('orientation');
            $table->string('status');
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Add indexes for ordering and filtering
            $table->index('room_id');
            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rows');
    }
};

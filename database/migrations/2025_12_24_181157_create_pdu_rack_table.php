<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create pdu_rack pivot table for many-to-many PDU-to-Rack relationships.
 *
 * This pivot table allows multiple PDUs to be assigned to multiple racks,
 * supporting redundant power configurations where a rack may be powered
 * by multiple PDUs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pdu_rack', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rack_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Add composite unique index to prevent duplicate assignments
            $table->unique(['pdu_id', 'rack_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdu_rack');
    }
};

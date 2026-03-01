<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add paired_port_id to ports table for patch panel port pairing.
 *
 * This self-referential foreign key allows patch panel front and back
 * ports to be linked, enabling logical path derivation through patch panels.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->foreignId('paired_port_id')
                ->nullable()
                ->after('direction')
                ->constrained('ports')
                ->nullOnDelete();

            $table->index('paired_port_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->dropForeign(['paired_port_id']);
            $table->dropIndex(['paired_port_id']);
            $table->dropColumn('paired_port_id');
        });
    }
};

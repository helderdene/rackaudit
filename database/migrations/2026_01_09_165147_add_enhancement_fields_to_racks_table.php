<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add enhancement fields to racks table for comprehensive rack information display.
 *
 * Adds manufacturer, model, depth, installation_date, location_notes, and specs
 * fields to support the enhanced Rack Show page with detailed physical specifications
 * and custom key-value specifications.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->string('manufacturer')->nullable()->after('serial_number');
            $table->string('model')->nullable()->after('manufacturer');
            $table->string('depth')->nullable()->after('model');
            $table->date('installation_date')->nullable()->after('depth');
            $table->text('location_notes')->nullable()->after('installation_date');
            $table->json('specs')->nullable()->after('location_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropColumn([
                'manufacturer',
                'model',
                'depth',
                'installation_date',
                'location_notes',
                'specs',
            ]);
        });
    }
};

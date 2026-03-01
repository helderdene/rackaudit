<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add support for half-U device sizes (0.5U).
 *
 * Changes u_height and default_u_size columns from integer to decimal
 * to support 0.5U devices commonly found in datacenters.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->decimal('u_height', 3, 1)->default(1)->change();
        });

        Schema::table('device_types', function (Blueprint $table) {
            $table->decimal('default_u_size', 3, 1)->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedTinyInteger('u_height')->default(1)->change();
        });

        Schema::table('device_types', function (Blueprint $table) {
            $table->integer('default_u_size')->default(1)->change();
        });
    }
};

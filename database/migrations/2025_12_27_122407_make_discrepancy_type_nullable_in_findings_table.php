<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Makes the discrepancy_type column nullable to support device verification
     * findings that don't have a connection discrepancy type.
     */
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->string('discrepancy_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->string('discrepancy_type')->nullable(false)->change();
        });
    }
};

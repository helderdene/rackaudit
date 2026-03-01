<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('distribution_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_list_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Prevent duplicate emails within a single distribution list
            $table->unique(['distribution_list_id', 'email']);

            // Index for ordering
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_list_members');
    }
};

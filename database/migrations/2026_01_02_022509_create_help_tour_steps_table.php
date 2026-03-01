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
        Schema::create('help_tour_steps', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('help_tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('help_article_id')->constrained()->cascadeOnDelete();

            // Step configuration
            $table->string('target_selector');
            $table->string('position')->default('bottom');
            $table->unsignedInteger('step_order')->default(0);

            $table->timestamps();

            // Composite index for ordering steps within a tour
            $table->index(['help_tour_id', 'step_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_tour_steps');
    }
};

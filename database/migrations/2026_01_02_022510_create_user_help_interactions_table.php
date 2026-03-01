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
        Schema::create('user_help_interactions', function (Blueprint $table) {
            $table->id();

            // User relationship
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Optional relationships to help content (one or the other will be set)
            $table->foreignId('help_article_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('help_tour_id')->nullable()->constrained()->cascadeOnDelete();

            // Interaction classification
            $table->string('interaction_type');

            $table->timestamps();

            // Indexes for common query patterns
            $table->index('user_id');
            $table->index('interaction_type');
            $table->index(['user_id', 'help_article_id', 'interaction_type'], 'user_article_interaction_idx');
            $table->index(['user_id', 'help_tour_id', 'interaction_type'], 'user_tour_interaction_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_help_interactions');
    }
};

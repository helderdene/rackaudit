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
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();

            // Unique identifier for URL routing
            $table->string('slug')->unique();

            // Article content
            $table->string('title');
            $table->text('content');

            // Context and classification
            $table->string('context_key')->nullable();
            $table->string('article_type');
            $table->string('category')->nullable();

            // Ordering and visibility
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common query patterns
            $table->index('context_key');
            $table->index('article_type');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};

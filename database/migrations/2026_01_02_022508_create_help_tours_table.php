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
        Schema::create('help_tours', function (Blueprint $table) {
            $table->id();

            // Unique identifier for URL routing
            $table->string('slug')->unique();

            // Tour details
            $table->string('name');
            $table->string('context_key')->nullable();
            $table->text('description')->nullable();

            // Visibility
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common query patterns
            $table->index('context_key');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_tours');
    }
};

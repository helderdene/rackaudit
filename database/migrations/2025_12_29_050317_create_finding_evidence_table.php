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
        Schema::create('finding_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')
                ->constrained('findings')
                ->cascadeOnDelete();
            $table->enum('type', ['file', 'text']);
            $table->text('content')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();

            // Index for finding lookups
            $table->index('finding_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finding_evidence');
    }
};

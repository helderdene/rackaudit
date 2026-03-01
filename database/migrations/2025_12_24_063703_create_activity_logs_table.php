<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['created', 'updated', 'deleted']);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('causer_id');
        });

        // Add generated columns and fulltext indexes for MySQL (skip for SQLite in testing)
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // Add generated columns for fulltext search on JSON columns
            DB::statement('ALTER TABLE `activity_logs` ADD COLUMN `old_values_text` TEXT GENERATED ALWAYS AS (CAST(`old_values` AS CHAR(10000))) STORED');
            DB::statement('ALTER TABLE `activity_logs` ADD COLUMN `new_values_text` TEXT GENERATED ALWAYS AS (CAST(`new_values` AS CHAR(10000))) STORED');

            // Add fulltext index on generated text columns
            DB::statement('ALTER TABLE `activity_logs` ADD FULLTEXT INDEX `activity_logs_values_fulltext` (`old_values_text`, `new_values_text`)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

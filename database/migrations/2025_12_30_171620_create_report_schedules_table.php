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
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distribution_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type');
            $table->json('report_configuration');
            $table->string('frequency');
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->string('day_of_month', 10)->nullable();
            $table->string('time_of_day', 5);
            $table->string('timezone')->default('UTC');
            $table->string('format');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index for scheduler queries to find due schedules
            $table->index(['is_enabled', 'next_run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};

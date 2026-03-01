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
        Schema::create('report_schedule_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedSmallInteger('recipients_count')->nullable();
            $table->timestamps();

            // Index for history queries
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_schedule_executions');
    }
};

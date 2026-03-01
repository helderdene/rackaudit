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
        Schema::table('findings', function (Blueprint $table) {
            // Check if column already exists before adding it
            if (! Schema::hasColumn('findings', 'audit_device_verification_id')) {
                $table->foreignId('audit_device_verification_id')
                    ->nullable()
                    ->after('audit_connection_verification_id')
                    ->constrained('audit_device_verifications')
                    ->nullOnDelete();

                $table->index('audit_device_verification_id');
            } else {
                // Column exists but foreign key may not be present
                // Add the foreign key constraint if missing
                $table->foreign('audit_device_verification_id')
                    ->references('id')
                    ->on('audit_device_verifications')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropForeign(['audit_device_verification_id']);
            $table->dropIndex(['audit_device_verification_id']);
            $table->dropColumn('audit_device_verification_id');
        });
    }
};

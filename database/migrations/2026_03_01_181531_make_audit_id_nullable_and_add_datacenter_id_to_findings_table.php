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
            $table->dropForeign(['audit_id']);
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->unsignedBigInteger('audit_id')->nullable()->change();
            $table->foreign('audit_id')->references('id')->on('audits')->nullOnDelete();
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->foreignId('datacenter_id')->nullable()->after('audit_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropForeign(['datacenter_id']);
            $table->dropColumn('datacenter_id');
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->dropForeign(['audit_id']);
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->unsignedBigInteger('audit_id')->nullable(false)->change();
            $table->foreign('audit_id')->references('id')->on('audits')->cascadeOnDelete();
        });
    }
};

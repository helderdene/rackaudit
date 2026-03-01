<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add location, contact, and floor plan fields to datacenters table.
 *
 * This migration extends the minimal datacenters table with full
 * datacenter management fields as specified in the Datacenter Management feature.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('datacenters', function (Blueprint $table) {
            // Location fields
            $table->string('address_line_1')->after('name');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city')->after('address_line_2');
            $table->string('state_province')->after('city');
            $table->string('postal_code')->after('state_province');
            $table->string('country')->after('postal_code');

            // Primary contact fields
            $table->string('company_name')->nullable()->after('country');
            $table->string('primary_contact_name')->after('company_name');
            $table->string('primary_contact_email')->after('primary_contact_name');
            $table->string('primary_contact_phone')->after('primary_contact_email');

            // Secondary contact fields (all optional)
            $table->string('secondary_contact_name')->nullable()->after('primary_contact_phone');
            $table->string('secondary_contact_email')->nullable()->after('secondary_contact_name');
            $table->string('secondary_contact_phone')->nullable()->after('secondary_contact_email');

            // Floor plan field
            $table->string('floor_plan_path')->nullable()->after('secondary_contact_phone');

            // Add index on name column for search optimization
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datacenters', function (Blueprint $table) {
            // Drop index first
            $table->dropIndex(['name']);

            // Drop all added columns
            $table->dropColumn([
                'address_line_1',
                'address_line_2',
                'city',
                'state_province',
                'postal_code',
                'country',
                'company_name',
                'primary_contact_name',
                'primary_contact_email',
                'primary_contact_phone',
                'secondary_contact_name',
                'secondary_contact_email',
                'secondary_contact_phone',
                'floor_plan_path',
            ]);
        });
    }
};

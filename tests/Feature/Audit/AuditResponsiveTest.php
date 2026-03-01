<?php

use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create IT Manager user for access
    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    // Create operator for assignees
    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create a datacenter for testing
    $this->datacenter = Datacenter::factory()->create();

    // Create an approved implementation file
    ImplementationFile::factory()
        ->approved()
        ->create(['datacenter_id' => $this->datacenter->id]);
});

/**
 * Test 1: Form renders correctly and includes responsive component structure
 *
 * This test verifies that the create page renders with all required components
 * that support responsive layouts. The actual responsive behavior is handled
 * by Tailwind CSS classes in the Vue components.
 */
test('audit create form renders with all responsive components', function () {
    $response = $this->actingAs($this->itManager)
        ->get('/audits/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Create')
            // Verify all required data for form components is present
            ->has('datacenters')
            ->has('assignableUsers')
            ->has('auditTypes')
            ->has('scopeTypes')
            // Verify audit types for type selector component
            ->has('auditTypes', 2, fn (Assert $type) => $type
                ->has('value')
                ->has('label')
            )
            // Verify scope types for scope selector component
            ->has('scopeTypes', 3, fn (Assert $scope) => $scope
                ->has('value')
                ->has('label')
            )
        );

    // Additional verification: Check that datacenters include location info for responsive display
    $response->assertInertia(fn (Assert $page) => $page
        ->has('datacenters.0', fn (Assert $datacenter) => $datacenter
            ->has('id')
            ->has('name')
            ->has('formatted_location')
            ->has('has_approved_implementation_files')
        )
    );
});

/**
 * Test 2: Multi-select components load data correctly for touch/mobile interaction
 *
 * This test verifies that the API endpoints for multi-select components
 * return properly structured data that supports touch-friendly interfaces.
 * The multi-select components use checkboxes which are touch-accessible.
 */
test('multi-select components provide accessible data for touch devices', function () {
    // Create rooms and racks for testing cascading dropdowns
    $room = \App\Models\Room::factory()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    $row = \App\Models\Row::factory()->create([
        'room_id' => $room->id,
    ]);

    $rack = \App\Models\Rack::factory()->create([
        'row_id' => $row->id,
    ]);

    $device = \App\Models\Device::factory()->create([
        'rack_id' => $rack->id,
    ]);

    // Test rooms API for cascading dropdown
    $roomsResponse = $this->actingAs($this->itManager)
        ->getJson("/api/audits/datacenters/{$this->datacenter->id}/rooms");

    $roomsResponse->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'rack_count', // Important for summary display
                ],
            ],
        ]);

    // Test racks API for multi-select component
    $racksResponse = $this->actingAs($this->itManager)
        ->getJson("/api/audits/rooms/{$room->id}/racks");

    $racksResponse->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'position',
                    'row_name',
                ],
            ],
        ]);

    // Test devices API for multi-select component
    $devicesResponse = $this->actingAs($this->itManager)
        ->getJson("/api/audits/racks/devices?rack_ids[]={$rack->id}");

    $devicesResponse->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'asset_tag',
                    'start_u',
                    'rack_id',
                    'rack_name',
                ],
            ],
        ]);

    // Test assignable users API for assignee multi-select
    $usersResponse = $this->actingAs($this->itManager)
        ->getJson('/api/audits/assignable-users');

    $usersResponse->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
        ]);
});

<?php

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Datacenter;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create hierarchy: Datacenter > Room > Row
    $this->datacenter = Datacenter::factory()->create(['name' => 'Elevation DC']);
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id, 'name' => 'Elevation Room']);
    $this->row = Row::factory()->create(['room_id' => $this->room->id, 'name' => 'Elevation Row']);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

/**
 * Test 1: Elevation page renders correct U-height value for slot rendering
 * The frontend uses u_height to generate U-slots numbered 1 to N
 */
test('elevation page renders with correct u_height for U-slot generation', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Test Rack 42U',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Elevation')
            ->where('rack.u_height', 42)
            ->where('rack.u_height_label', '42U')
        );
});

/**
 * Test 2: Elevation page works with different U-heights (45U and 48U)
 * Verifies correct u_height values are passed for all supported rack heights
 */
test('elevation page supports all rack U-heights (42U, 45U, 48U)', function (RackUHeight $uHeight, int $expectedValue, string $expectedLabel) {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => "Rack {$expectedLabel}",
        'u_height' => $uHeight,
        'status' => RackStatus::Active,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Elevation')
            ->where('rack.u_height', $expectedValue)
            ->where('rack.u_height_label', $expectedLabel)
        );
})->with([
    'U42' => [RackUHeight::U42, 42, '42U'],
    'U45' => [RackUHeight::U45, 45, '45U'],
    'U48' => [RackUHeight::U48, 48, '48U'],
]);

/**
 * Test 3: Elevation page displays rack details in header
 * Tests that rack name, status, and hierarchy information are passed
 */
test('elevation page displays rack details and hierarchy information', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Header Details Rack',
        'position' => 5,
        'u_height' => RackUHeight::U48,
        'serial_number' => 'SN-ELEVATION-123',
        'status' => RackStatus::Maintenance,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Elevation')
            // Rack details in header
            ->where('rack.name', 'Header Details Rack')
            ->where('rack.position', 5)
            ->where('rack.u_height', 48)
            ->where('rack.u_height_label', '48U')
            ->where('rack.serial_number', 'SN-ELEVATION-123')
            ->where('rack.status', RackStatus::Maintenance->value)
            ->where('rack.status_label', 'Maintenance')
            // Hierarchy data for breadcrumbs
            ->where('datacenter.name', 'Elevation DC')
            ->where('room.name', 'Elevation Room')
            ->where('row.name', 'Elevation Row')
        );
});

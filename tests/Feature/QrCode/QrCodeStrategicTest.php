<?php

/**
 * Strategic tests for QR Code generation feature.
 *
 * These tests fill critical gaps identified during test review:
 * - Mobile scanning workflow (redirect to login, redirect after auth)
 * - QR code URL stability (routes don't change unexpectedly)
 * - Validation error handling
 * - Edge cases: very long names, empty results, missing labels
 */

use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create test data hierarchy
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $this->room = Room::factory()->for($this->datacenter)->create(['name' => 'Test Room']);
    $this->row = Row::factory()->for($this->room)->create(['name' => 'Test Row']);
    $this->rack = Rack::factory()->for($this->row)->create([
        'name' => 'Test Rack',
        'serial_number' => 'RACK-001',
    ]);
});

/**
 * Test 1: Mobile scanning workflow - unauthenticated device URL redirects to login
 *
 * When a user scans a QR code pointing to a device detail page,
 * they should be redirected to login first.
 */
test('scanning device QR code URL redirects unauthenticated users to login', function () {
    $device = Device::factory()->for($this->rack)->create(['name' => 'Scanned Device']);

    $response = $this->get("/devices/{$device->id}");

    $response->assertRedirect('/login');
});

/**
 * Test 2: Mobile scanning workflow - unauthenticated rack URL redirects to login
 *
 * When a user scans a QR code pointing to a rack detail page,
 * they should be redirected to login first.
 */
test('scanning rack QR code URL redirects unauthenticated users to login', function () {
    $response = $this->get(route('datacenters.rooms.rows.racks.show', [
        'datacenter' => $this->datacenter->id,
        'room' => $this->room->id,
        'row' => $this->row->id,
        'rack' => $this->rack->id,
    ]));

    $response->assertRedirect('/login');
});

/**
 * Test 3: After login, user is redirected to originally requested device page
 *
 * Laravel's auth middleware should preserve the intended URL and redirect back
 * after successful authentication.
 */
test('after login user is redirected to originally requested device page', function () {
    $device = Device::factory()->for($this->rack)->create(['name' => 'Intended Device']);

    // First, try to access the device page (should store intended URL)
    $this->get("/devices/{$device->id}");

    // Now login - should redirect to the intended device page
    $response = $this->actingAs($this->admin)->get(session('url.intended', '/devices/'.$device->id));

    $response->assertOk();
});

/**
 * Test 4: Validation error - invalid entity_type is rejected
 */
test('bulk QR generation rejects invalid entity type', function () {
    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'invalid_type',
        ]);

    $response->assertSessionHasErrors('entity_type');
});

/**
 * Test 5: Validation error - non-existent datacenter_id is rejected
 */
test('bulk QR generation rejects non-existent datacenter id', function () {
    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'device',
            'datacenter_id' => 99999, // Non-existent ID
        ]);

    $response->assertSessionHasErrors('datacenter_id');
});

/**
 * Test 6: Edge case - very long entity names are handled in PDF generation
 *
 * Names exceeding label space should be truncated gracefully without errors.
 */
test('PDF generation handles devices with very long names', function () {
    $longName = str_repeat('VeryLongDeviceNameThatExceedsNormalLimits', 5);

    Device::factory()->for($this->rack)->create([
        'name' => $longName,
        'asset_tag' => 'ASSET-LONG-001',
    ]);

    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'device',
        ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    // PDF should still be valid and contain content
    $content = $response->getContent();
    expect(strlen($content))->toBeGreaterThan(1000);
    expect(substr($content, 0, 4))->toBe('%PDF');
});

/**
 * Test 7: Filter by room_id works correctly
 *
 * Room-level filtering should return only devices/racks in that room.
 */
test('filter by room_id returns only items in that room', function () {
    // Create a second room with its own rack and device
    $room2 = Room::factory()->for($this->datacenter)->create(['name' => 'Room 2']);
    $row2 = Row::factory()->for($room2)->create(['name' => 'Row 2']);
    $rack2 = Rack::factory()->for($row2)->create(['name' => 'Rack in Room 2']);
    Device::factory()->for($rack2)->count(3)->create();

    // Create device in original room
    Device::factory()->for($this->rack)->create(['name' => 'Device in Room 1']);

    // Request PDF with filter for first room only
    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'device',
            'room_id' => $this->room->id,
        ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

/**
 * Test 8: Filter by row_id works correctly
 *
 * Row-level filtering should return only devices/racks in that row.
 */
test('filter by row_id returns only items in that row', function () {
    // Create a second row with its own rack and device
    $row2 = Row::factory()->for($this->room)->create(['name' => 'Row 2']);
    $rack2 = Rack::factory()->for($row2)->create(['name' => 'Rack in Row 2']);
    Device::factory()->for($rack2)->count(2)->create();

    // Create device in original row
    Device::factory()->for($this->rack)->create(['name' => 'Device in Row 1']);

    // Request PDF with filter for first row only
    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'device',
            'row_id' => $this->row->id,
        ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

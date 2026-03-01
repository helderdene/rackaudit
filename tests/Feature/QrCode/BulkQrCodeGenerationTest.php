<?php

use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('Viewer');

    // Create test data hierarchy
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $this->room = Room::factory()->for($this->datacenter)->create(['name' => 'Test Room']);
    $this->row = Row::factory()->for($this->room)->create(['name' => 'Test Row']);
    $this->rack = Rack::factory()->for($this->row)->create([
        'name' => 'Test Rack',
        'serial_number' => 'RACK-001',
    ]);
    $this->device = Device::factory()->for($this->rack)->create([
        'name' => 'Test Device',
    ]);
});

/**
 * Test 1: Bulk QR page loads for devices
 */
test('bulk QR page loads for devices', function () {
    $response = $this->actingAs($this->admin)
        ->get('/qr-codes/bulk');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('QrCodes/Bulk', shouldExist: false)
            ->has('entityTypeOptions')
            ->has('filterOptions.datacenters')
            ->has('filterOptions.rooms')
            ->has('filterOptions.rows')
            ->has('filterOptions.racks')
        );
});

/**
 * Test 2: Bulk QR page loads for racks (verifies entity type options include racks)
 */
test('bulk QR page includes rack entity type option', function () {
    $response = $this->actingAs($this->admin)
        ->get('/qr-codes/bulk');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entityTypeOptions', function ($options) {
                $values = collect($options)->pluck('value')->toArray();

                return in_array('rack', $values) && in_array('device', $values);
            })
        );
});

/**
 * Test 3: PDF generation with multiple devices
 */
test('PDF generation with multiple devices', function () {
    // Create additional devices
    Device::factory()
        ->for($this->rack)
        ->count(5)
        ->create();

    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'device',
        ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition');

    // Verify filename format
    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('qr-codes-device-');
    expect($disposition)->toContain('.pdf');
});

/**
 * Test 4: PDF generation with multiple racks
 */
test('PDF generation with multiple racks', function () {
    // Create additional racks
    Rack::factory()
        ->for($this->row)
        ->count(3)
        ->create();

    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'rack',
        ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    // Verify filename contains rack entity type
    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('qr-codes-rack-');
});

/**
 * Test 5: Filter by datacenter/room/row/rack hierarchy works
 */
test('filter by datacenter/room/row/rack hierarchy works', function () {
    // Create a second datacenter with its own hierarchy
    $datacenter2 = Datacenter::factory()->create(['name' => 'Other DC']);
    $room2 = Room::factory()->for($datacenter2)->create();
    $row2 = Row::factory()->for($room2)->create();
    $rack2 = Rack::factory()->for($row2)->create();
    Device::factory()->for($rack2)->count(5)->create();

    // Request PDF with filter for first datacenter only
    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'device',
            'datacenter_id' => $this->datacenter->id,
        ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    // Request PDF with filter for specific rack
    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'device',
            'rack_id' => $this->rack->id,
        ]);

    $response->assertOk();
});

/**
 * Test 6: Viewer role users have access to bulk QR generation
 */
test('viewer role users have access to bulk QR generation', function () {
    // Viewer role should have access (all authenticated users with view access)
    $response = $this->actingAs($this->viewer)
        ->get('/qr-codes/bulk');

    $response->assertOk();
});

/**
 * Test 7: Unauthenticated users are redirected to login
 */
test('unauthenticated users are redirected to login', function () {
    $response = $this->get('/qr-codes/bulk');
    $response->assertRedirect('/login');
});

/**
 * Test 8: PDF download is valid and contains content
 */
test('PDF download is valid and contains content', function () {
    // Create devices with specific names for validation
    $devices = Device::factory()
        ->for($this->rack)
        ->count(3)
        ->sequence(
            ['name' => 'Server Alpha'],
            ['name' => 'Server Beta'],
            ['name' => 'Server Gamma'],
        )
        ->create();

    $response = $this->actingAs($this->admin)
        ->post('/qr-codes/bulk', [
            'entity_type' => 'device',
        ]);

    $response->assertOk();

    // Verify PDF has content (non-empty response)
    $content = $response->getContent();
    expect(strlen($content))->toBeGreaterThan(1000);

    // Verify it starts with PDF header
    expect(substr($content, 0, 4))->toBe('%PDF');
});

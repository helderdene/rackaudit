<?php

/**
 * Additional strategic tests to fill coverage gaps for Port Management feature.
 *
 * These tests cover edge cases and integration scenarios not covered by the
 * initial 18 tests from Task Groups 1-3.
 */

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\PortVisualFace;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('bulk creation works with maximum range of 100 ports', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $bulkData = [
        'prefix' => 'port',
        'start_number' => 1,
        'end_number' => 100,
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Gbe10->value,
        'direction' => PortDirection::Bidirectional->value,
    ];

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports/bulk", $bulkData);

    $response->assertCreated();
    $response->assertJsonCount(100, 'data');

    // Verify all 100 ports were created
    expect(Port::where('device_id', $device->id)->count())->toBe(100);

    // Verify first and last labels
    $this->assertDatabaseHas('ports', ['device_id' => $device->id, 'label' => 'port1']);
    $this->assertDatabaseHas('ports', ['device_id' => $device->id, 'label' => 'port100']);
});

test('bulk creation fails when exceeding 100 port limit', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $bulkData = [
        'prefix' => 'port',
        'start_number' => 1,
        'end_number' => 101, // Would create 101 ports
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Gbe10->value,
    ];

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports/bulk", $bulkData);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['end_number']);
});

test('position fields (slot, row, column) are stored correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $portData = [
        'label' => 'patch-1-1-12',
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Gbe1->value,
        'direction' => PortDirection::Bidirectional->value,
        'position_slot' => 1,
        'position_row' => 3,
        'position_column' => 12,
    ];

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports", $portData);

    $response->assertCreated();

    // Verify position fields are stored in database
    $this->assertDatabaseHas('ports', [
        'device_id' => $device->id,
        'label' => 'patch-1-1-12',
        'position_slot' => 1,
        'position_row' => 3,
        'position_column' => 12,
    ]);

    // Verify position fields are returned in API response
    $response->assertJsonFragment([
        'position_slot' => 1,
        'position_row' => 3,
        'position_column' => 12,
    ]);
});

test('visual position fields (visual_x, visual_y, visual_face) are stored correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $portData = [
        'label' => 'visual-port',
        'type' => PortType::Fiber->value,
        'subtype' => PortSubtype::Lc->value,
        'direction' => PortDirection::Bidirectional->value,
        'visual_x' => 25.50,
        'visual_y' => 75.25,
        'visual_face' => PortVisualFace::Front->value,
    ];

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports", $portData);

    $response->assertCreated();

    // Verify visual position fields are stored in database
    $this->assertDatabaseHas('ports', [
        'device_id' => $device->id,
        'label' => 'visual-port',
        'visual_face' => 'front',
    ]);

    // Check decimal values in database
    $port = Port::where('label', 'visual-port')->first();
    expect((float) $port->visual_x)->toBe(25.50);
    expect((float) $port->visual_y)->toBe(75.25);
    expect($port->visual_face)->toBe(PortVisualFace::Front);
});

test('port label must be unique within same device', function () {
    $device = Device::factory()->create();

    // Create first port with label 'eth0'
    Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    // Attempting to create another port with same label on same device should fail
    expect(fn () => Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]))->toThrow(QueryException::class);

    // But same label on different device should work
    $device2 = Device::factory()->create();
    $port = Port::factory()->create([
        'device_id' => $device2->id,
        'label' => 'eth0',
    ]);

    expect($port->label)->toBe('eth0');
});

test('validation error messages are properly formatted', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    // Test with invalid data to trigger validation errors
    $portData = [
        // Missing label
        'type' => 'invalid-type',
        'subtype' => 'invalid-subtype',
        'position_slot' => -1,
        'visual_x' => 150, // Over 100
    ];

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports", $portData);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['label', 'type', 'subtype', 'position_slot', 'visual_x']);

    // Verify error messages are human-readable
    $response->assertJsonFragment(['The port label is required.']);
});

test('integration: full flow of adding ports then viewing on Device Show', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    // Step 1: Create a single port via API
    $singlePortData = [
        'label' => 'eth0',
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Gbe10->value,
        'status' => PortStatus::Available->value,
        'direction' => PortDirection::Uplink->value,
    ];

    $createResponse = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports", $singlePortData);

    $createResponse->assertCreated();

    // Step 2: Create bulk ports via API
    $bulkData = [
        'prefix' => 'port',
        'start_number' => 1,
        'end_number' => 3,
        'type' => PortType::Fiber->value,
        'subtype' => PortSubtype::Lc->value,
    ];

    $bulkResponse = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports/bulk", $bulkData);

    $bulkResponse->assertCreated();
    $bulkResponse->assertJsonCount(3, 'data');

    // Step 3: View Device Show page and verify ports are displayed
    $showResponse = $this->actingAs($user)
        ->get("/devices/{$device->id}");

    $showResponse->assertSuccessful();
    $showResponse->assertInertia(fn (Assert $page) => $page
        ->component('Devices/Show')
        ->has('ports', 4) // 1 single + 3 bulk = 4 ports
        ->where('ports.0.label', 'eth0')
        ->where('ports.0.type', 'ethernet')
        ->where('ports.0.subtype', 'gbe10')
        ->where('ports.0.direction', 'uplink')
    );

    // Verify all ports exist in database
    expect(Port::where('device_id', $device->id)->count())->toBe(4);
});

test('empty state is returned when device has no ports and options are available', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    // Device has no ports - verify empty state with form options
    $response = $this->actingAs($user)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Devices/Show')
        ->has('ports', 0)
        ->where('canEdit', true)
        // Verify enum options are available for add forms
        ->has('portTypeOptions', function (Assert $options) {
            $options->etc();
        })
        ->has('portSubtypeOptions', function (Assert $options) {
            $options->etc();
        })
        ->has('portStatusOptions', function (Assert $options) {
            $options->etc();
        })
        ->has('portDirectionOptions', function (Assert $options) {
            $options->etc();
        })
    );

    // Verify we can add a port from this empty state
    $portData = [
        'label' => 'first-port',
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Gbe1->value,
        'direction' => PortDirection::Bidirectional->value,
    ];

    $createResponse = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports", $portData);

    $createResponse->assertCreated();

    // Verify the port was added
    expect(Port::where('device_id', $device->id)->count())->toBe(1);
});

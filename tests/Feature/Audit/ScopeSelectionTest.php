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

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    // Create datacenter hierarchy for testing
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->for($this->datacenter)->create();
    $this->row = Row::factory()->for($this->room)->create();
    $this->rack = Rack::factory()->for($this->row)->create();
    $this->device = Device::factory()->placed($this->rack, 1)->create();

    $this->operator = User::factory()->create(['status' => 'active']);
    $this->operator->assignRole('Operator');
});

/**
 * Test 1: Scope type options render correctly in the create form
 */
test('scope type options render correctly', function () {
    $response = $this->actingAs($this->itManager)
        ->get('/audits/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Create')
            ->has('scopeTypes', 3)
            ->has('scopeTypes.0', fn (Assert $scope) => $scope
                ->where('value', 'datacenter')
                ->where('label', 'Datacenter')
            )
            ->has('scopeTypes.1', fn (Assert $scope) => $scope
                ->where('value', 'room')
                ->where('label', 'Room')
            )
            ->has('scopeTypes.2', fn (Assert $scope) => $scope
                ->where('value', 'racks')
                ->where('label', 'Racks')
            )
        );
});

/**
 * Test 2: Datacenter dropdown loads and rooms API cascades correctly
 */
test('datacenter dropdown loads and rooms API cascades correctly', function () {
    // Create additional rooms with racks for the datacenter
    $room2 = Room::factory()->for($this->datacenter)->create(['name' => 'Server Room B']);
    $row2 = Row::factory()->for($room2)->create();
    Rack::factory()->count(5)->for($row2)->create();

    // Verify datacenters are passed to the create form
    $response = $this->actingAs($this->itManager)
        ->get('/audits/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('datacenters', 1)
            ->has('datacenters.0', fn (Assert $dc) => $dc
                ->where('id', $this->datacenter->id)
                ->where('name', $this->datacenter->name)
                ->has('formatted_location')
                ->has('has_approved_implementation_files')
            )
        );

    // Test the rooms API endpoint cascades from datacenter
    $roomsResponse = $this->actingAs($this->itManager)
        ->getJson("/api/audits/datacenters/{$this->datacenter->id}/rooms");

    $roomsResponse->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'rack_count'],
            ],
        ]);

    // Verify rack counts are included
    $rooms = $roomsResponse->json('data');
    $room2Data = collect($rooms)->firstWhere('id', $room2->id);
    expect($room2Data['rack_count'])->toBe(5);
});

/**
 * Test 3: Rack multi-select works with room filtering via API
 */
test('rack multi-select works with room filtering via API', function () {
    // Create additional racks in the room
    Rack::factory()->count(3)->for($this->row)->create();

    // Test the racks API endpoint
    $racksResponse = $this->actingAs($this->itManager)
        ->getJson("/api/audits/rooms/{$this->room->id}/racks");

    $racksResponse->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'position', 'row_name'],
            ],
        ]);

    // Create audit with specific racks scope
    $rackIds = $racksResponse->json('data.*.id');

    $storeResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Rack Selection Test',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => 'inventory',
            'scope_type' => 'racks',
            'datacenter_id' => $this->datacenter->id,
            'rack_ids' => array_slice($rackIds, 0, 2),
            'assignee_ids' => [$this->operator->id],
        ]);

    $storeResponse->assertRedirect('/audits');

    $this->assertDatabaseHas('audits', [
        'name' => 'Rack Selection Test',
        'scope_type' => 'racks',
    ]);

    // Verify only selected racks are attached
    $audit = \App\Models\Audit::where('name', 'Rack Selection Test')->first();
    expect($audit->racks)->toHaveCount(2);
});

/**
 * Test 4: Device selection only works for rack scope and fetches devices correctly
 */
test('device selection only works for rack scope and fetches devices correctly', function () {
    // Create additional devices in the rack
    Device::factory()->placed($this->rack, 5)->create(['name' => 'Device 2']);
    Device::factory()->placed($this->rack, 10)->create(['name' => 'Device 3']);

    // Test the devices API endpoint with rack_ids
    $devicesResponse = $this->actingAs($this->itManager)
        ->getJson("/api/audits/racks/devices?rack_ids[]={$this->rack->id}");

    $devicesResponse->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'asset_tag', 'start_u', 'rack_id', 'rack_name'],
            ],
        ]);

    // Verify device fields contain expected data
    $devices = $devicesResponse->json('data');
    foreach ($devices as $device) {
        expect($device)->toHaveKeys(['id', 'name', 'asset_tag', 'start_u', 'rack_id', 'rack_name']);
        expect($device['rack_id'])->toBe($this->rack->id);
    }

    // Create audit with specific devices
    $deviceIds = array_slice(array_column($devices, 'id'), 0, 2);

    $storeResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Device Selection Test',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => 'inventory',
            'scope_type' => 'racks',
            'datacenter_id' => $this->datacenter->id,
            'rack_ids' => [$this->rack->id],
            'device_ids' => $deviceIds,
            'assignee_ids' => [$this->operator->id],
        ]);

    $storeResponse->assertRedirect('/audits');

    // Verify devices are attached
    $audit = \App\Models\Audit::where('name', 'Device Selection Test')->first();
    expect($audit->devices)->toHaveCount(2);

    // Verify device_ids are rejected for non-rack scope types with validation error
    $invalidResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Invalid Device Selection',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => 'inventory',
            'scope_type' => 'datacenter',
            'datacenter_id' => $this->datacenter->id,
            'device_ids' => $deviceIds,
            'assignee_ids' => [$this->operator->id],
        ]);

    // Should fail validation because device_ids is not allowed for datacenter scope
    $invalidResponse->assertSessionHasErrors(['device_ids']);
});

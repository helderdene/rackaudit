<?php

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

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->auditor = User::factory()->create(['status' => 'active']);
    $this->auditor->assignRole('Auditor');

    $this->operator = User::factory()->create(['status' => 'active']);
    $this->operator->assignRole('Operator');

    // Create datacenter with rooms, rows, racks, and devices
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->for($this->datacenter)->create();
    $this->row = Row::factory()->for($this->room)->create();
    $this->rack = Rack::factory()->for($this->row)->create();
    $this->device = Device::factory()->placed($this->rack, 1)->create();
});

/**
 * Test 1: Rooms endpoint returns rooms for a datacenter with rack counts
 */
test('rooms endpoint returns rooms for datacenter with rack counts', function () {
    // Create additional rooms with rows and racks
    $room2 = Room::factory()->for($this->datacenter)->create();
    $row2 = Row::factory()->for($room2)->create();
    Rack::factory()->count(3)->for($row2)->create();

    $response = $this->actingAs($this->admin)
        ->getJson("/api/audits/datacenters/{$this->datacenter->id}/rooms");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'rack_count',
                ],
            ],
        ]);

    // Verify the rack counts are correct
    $rooms = $response->json('data');
    $room2Data = collect($rooms)->firstWhere('id', $room2->id);
    expect($room2Data['rack_count'])->toBe(3);
});

/**
 * Test 2: Racks endpoint returns racks for a room
 */
test('racks endpoint returns racks for room', function () {
    // Create additional racks in the room's row
    Rack::factory()->count(2)->for($this->row)->create();

    $response = $this->actingAs($this->admin)
        ->getJson("/api/audits/rooms/{$this->room->id}/racks");

    $response->assertOk()
        ->assertJsonCount(3, 'data')
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
});

/**
 * Test 3: Devices endpoint returns devices for rack(s) with required fields
 */
test('devices endpoint returns devices for racks with asset_tag and start_u', function () {
    // Create additional devices in the rack
    Device::factory()->placed($this->rack, 5)->create();
    Device::factory()->placed($this->rack, 10)->create();

    $response = $this->actingAs($this->admin)
        ->getJson("/api/audits/racks/devices?rack_ids[]={$this->rack->id}");

    $response->assertOk()
        ->assertJsonCount(3, 'data')
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

    // Verify devices have the expected fields populated
    $devices = $response->json('data');
    foreach ($devices as $device) {
        expect($device)->toHaveKeys(['id', 'name', 'asset_tag', 'start_u', 'rack_id', 'rack_name']);
    }
});

/**
 * Test 4: Assignable users endpoint returns users who can execute audits
 */
test('assignable users endpoint returns users with Operator or Auditor roles', function () {
    // Create additional users with different roles
    $inactiveOperator = User::factory()->create(['status' => 'inactive']);
    $inactiveOperator->assignRole('Operator');

    $anotherAuditor = User::factory()->create(['status' => 'active']);
    $anotherAuditor->assignRole('Auditor');

    $response = $this->actingAs($this->admin)
        ->getJson('/api/audits/assignable-users');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
        ]);

    // Should only include active users with Operator or Auditor roles
    $users = $response->json('data');
    $userIds = collect($users)->pluck('id')->toArray();

    // Active operator and auditors should be included
    expect($userIds)->toContain($this->operator->id);
    expect($userIds)->toContain($this->auditor->id);
    expect($userIds)->toContain($anotherAuditor->id);

    // Inactive operator should NOT be included
    expect($userIds)->not->toContain($inactiveOperator->id);

    // Admin and IT Manager should NOT be included (they create audits, not execute)
    expect($userIds)->not->toContain($this->admin->id);
    expect($userIds)->not->toContain($this->itManager->id);
});

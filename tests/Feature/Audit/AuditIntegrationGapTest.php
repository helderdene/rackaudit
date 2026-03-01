<?php

/**
 * Strategic Integration Tests for Audit Creation Feature
 *
 * These tests fill critical gaps identified during the Task Group 12 review,
 * focusing on integration points, edge cases, and cross-cutting concerns
 * that weren't explicitly covered in Task Groups 1-11.
 */

use App\Enums\AuditScopeType;
use App\Enums\AuditType;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ImplementationFile;
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

    $this->operator = User::factory()->create(['status' => 'active']);
    $this->operator->assignRole('Operator');

    // Create primary datacenter with hierarchy
    $this->datacenter = Datacenter::factory()->create(['name' => 'Primary DC']);
    $this->room = Room::factory()->for($this->datacenter)->create(['name' => 'Server Room A']);
    $this->row = Row::factory()->for($this->room)->create();
    $this->rack1 = Rack::factory()->for($this->row)->create(['name' => 'Rack A1']);
    $this->rack2 = Rack::factory()->for($this->row)->create(['name' => 'Rack A2']);
    $this->device1 = Device::factory()->placed($this->rack1, 1)->create(['name' => 'Server 01']);
    $this->device2 = Device::factory()->placed($this->rack1, 5)->create(['name' => 'Server 02']);
    $this->device3 = Device::factory()->placed($this->rack2, 1)->create(['name' => 'Server 03']);

    // Create secondary datacenter for cross-datacenter validation tests
    $this->datacenter2 = Datacenter::factory()->create(['name' => 'Secondary DC']);
    $this->room2 = Room::factory()->for($this->datacenter2)->create(['name' => 'Server Room B']);
    $this->row2 = Row::factory()->for($this->room2)->create();
    $this->rack3 = Rack::factory()->for($this->row2)->create(['name' => 'Rack B1']);
    $this->device4 = Device::factory()->placed($this->rack3, 1)->create(['name' => 'Server 04']);
});

/**
 * Test 1: Administrator can create audits (additional role verification)
 *
 * Gap: StoreAuditRequestTest only tested IT Manager and Auditor roles.
 * Administrator is also in AUTHORIZED_ROLES and should be able to create audits.
 */
test('Administrator can create audits', function () {
    // Create approved implementation file for connection audit
    ImplementationFile::factory()
        ->for($this->datacenter)
        ->approved()
        ->create();

    $response = $this->actingAs($this->admin)
        ->post('/audits', [
            'name' => 'Admin Created Audit',
            'description' => 'Audit created by administrator',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Connection->value,
            'scope_type' => AuditScopeType::Datacenter->value,
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$this->operator->id],
        ]);

    $response->assertRedirect('/audits');
    $response->assertSessionHas('success');

    $audit = Audit::where('name', 'Admin Created Audit')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->created_by)->toBe($this->admin->id);
});

/**
 * Test 2: Room ID must belong to the selected datacenter
 *
 * Gap: Room scope validation only checked that room_id exists,
 * not that it belongs to the selected datacenter.
 */
test('room_id must belong to the selected datacenter', function () {
    // Try to create audit with room from different datacenter
    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Cross-DC Room Audit',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Inventory->value,
            'scope_type' => AuditScopeType::Room->value,
            'datacenter_id' => $this->datacenter->id,
            'room_id' => $this->room2->id, // Room from datacenter2
            'assignee_ids' => [$this->operator->id],
        ]);

    $response->assertSessionHasErrors(['room_id']);

    // Verify valid room from correct datacenter works
    $validResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Valid Room Audit',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Inventory->value,
            'scope_type' => AuditScopeType::Room->value,
            'datacenter_id' => $this->datacenter->id,
            'room_id' => $this->room->id,
            'assignee_ids' => [$this->operator->id],
        ]);

    $validResponse->assertRedirect('/audits');
});

/**
 * Test 3: Rack IDs must belong to the selected datacenter
 *
 * Gap: Rack scope validation only checked that rack_ids exist,
 * not that they belong to the selected datacenter.
 */
test('rack_ids must belong to the selected datacenter', function () {
    // Try to create audit with rack from different datacenter
    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Cross-DC Rack Audit',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Inventory->value,
            'scope_type' => AuditScopeType::Racks->value,
            'datacenter_id' => $this->datacenter->id,
            'rack_ids' => [$this->rack1->id, $this->rack3->id], // rack3 is from datacenter2
            'assignee_ids' => [$this->operator->id],
        ]);

    $response->assertSessionHasErrors(['rack_ids']);

    // Verify valid racks from correct datacenter work
    $validResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Valid Rack Audit',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Inventory->value,
            'scope_type' => AuditScopeType::Racks->value,
            'datacenter_id' => $this->datacenter->id,
            'rack_ids' => [$this->rack1->id, $this->rack2->id],
            'assignee_ids' => [$this->operator->id],
        ]);

    $validResponse->assertRedirect('/audits');

    $audit = Audit::where('name', 'Valid Rack Audit')->first();
    expect($audit->racks)->toHaveCount(2);
});

/**
 * Test 4: Device IDs must belong to the selected racks
 *
 * Gap: Device selection validation only checked that device_ids exist,
 * not that they belong to the selected racks.
 */
test('device_ids must belong to the selected racks', function () {
    // Try to create audit with device from non-selected rack
    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Invalid Device Audit',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Inventory->value,
            'scope_type' => AuditScopeType::Racks->value,
            'datacenter_id' => $this->datacenter->id,
            'rack_ids' => [$this->rack1->id],
            'device_ids' => [$this->device1->id, $this->device3->id], // device3 is in rack2
            'assignee_ids' => [$this->operator->id],
        ]);

    $response->assertSessionHasErrors(['device_ids']);

    // Verify valid devices from selected rack work
    $validResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Valid Device Audit',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Inventory->value,
            'scope_type' => AuditScopeType::Racks->value,
            'datacenter_id' => $this->datacenter->id,
            'rack_ids' => [$this->rack1->id],
            'device_ids' => [$this->device1->id, $this->device2->id],
            'assignee_ids' => [$this->operator->id],
        ]);

    $validResponse->assertRedirect('/audits');

    $audit = Audit::where('name', 'Valid Device Audit')->first();
    expect($audit->devices)->toHaveCount(2);
    expect($audit->racks)->toHaveCount(1);
});

/**
 * Test 5: Datacenter scope does not attach specific racks but covers all implicitly
 *
 * Gap: Verify that datacenter scope audits do not have specific racks attached
 * but the scope type indicates the entire datacenter is covered.
 */
test('datacenter scope audit covers all racks without explicit attachment', function () {
    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Datacenter Scope Coverage',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Inventory->value,
            'scope_type' => AuditScopeType::Datacenter->value,
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$this->operator->id],
        ]);

    $response->assertRedirect('/audits');

    $audit = Audit::where('name', 'Datacenter Scope Coverage')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->scope_type)->toBe(AuditScopeType::Datacenter)
        ->and($audit->datacenter_id)->toBe($this->datacenter->id)
        // Datacenter scope should NOT have specific racks attached
        ->and($audit->racks)->toHaveCount(0)
        ->and($audit->devices)->toHaveCount(0)
        // But room_id should also be null for datacenter scope
        ->and($audit->room_id)->toBeNull();

    // Verify all racks in the datacenter can be queried through the relationship
    $datacenterRackCount = $this->datacenter->rooms()
        ->with('rows.racks')
        ->get()
        ->flatMap(fn ($room) => $room->rows->flatMap(fn ($row) => $row->racks))
        ->count();

    expect($datacenterRackCount)->toBe(2); // rack1 and rack2
});

/**
 * Test 6: Connection audit returns proper error with link when no approved file exists
 *
 * Gap: While validation is tested, verify the error message contains
 * actionable information (link to implementation files page).
 */
test('connection audit error provides actionable guidance when no approved file exists', function () {
    // Ensure datacenter has no approved files
    expect($this->datacenter->hasApprovedImplementationFiles())->toBeFalse();

    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Missing File Audit',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Connection->value,
            'scope_type' => AuditScopeType::Datacenter->value,
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$this->operator->id],
        ]);

    $response->assertSessionHasErrors(['type']);

    // Verify error message contains guidance
    $errors = session('errors')->get('type');
    expect($errors[0])
        ->toContain('approved implementation file')
        ->toContain('Implementation Files page');

    // Now test that the API endpoint also provides actionable information
    $apiResponse = $this->actingAs($this->itManager)
        ->getJson("/api/audits/datacenters/{$this->datacenter->id}/implementation-file-status");

    $apiResponse->assertOk()
        ->assertJson([
            'has_approved_file' => false,
        ])
        ->assertJsonStructure([
            'implementation_files_url',
            'error_message',
        ]);

    $data = $apiResponse->json();
    expect($data['implementation_files_url'])->toContain('implementation-files');
    expect($data['error_message'])->toContain('approved implementation file');
});

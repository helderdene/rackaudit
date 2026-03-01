<?php

use App\Enums\AuditScopeType;
use App\Enums\AuditType;
use App\Http\Requests\StoreAuditRequest;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ImplementationFile;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create a temporary route that uses StoreAuditRequest for testing validation
    Route::post('/test-audit-store', function (StoreAuditRequest $request) {
        return response()->json(['message' => 'Validation passed', 'data' => $request->validated()]);
    })->middleware(['web']);

    // Create test users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('Auditor');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create test datacenter
    $this->datacenter = Datacenter::factory()->create();

    // Create assignee users
    $this->assignee = User::factory()->create();
});

test('IT Manager can create audits', function () {
    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', [
        'name' => 'Q1 2025 Inventory Audit',
        'description' => 'Quarterly inventory verification',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Inventory->value,
        'scope_type' => AuditScopeType::Datacenter->value,
        'datacenter_id' => $this->datacenter->id,
        'assignee_ids' => [$this->assignee->id],
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['message' => 'Validation passed']);
});

test('Auditor can create audits', function () {
    $response = $this->actingAs($this->auditor)->postJson('/test-audit-store', [
        'name' => 'Q1 2025 Inventory Audit',
        'description' => 'Quarterly inventory verification',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Inventory->value,
        'scope_type' => AuditScopeType::Datacenter->value,
        'datacenter_id' => $this->datacenter->id,
        'assignee_ids' => [$this->assignee->id],
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['message' => 'Validation passed']);
});

test('Operator cannot create audits', function () {
    $response = $this->actingAs($this->operator)->postJson('/test-audit-store', [
        'name' => 'Q1 2025 Inventory Audit',
        'description' => 'Quarterly inventory verification',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Inventory->value,
        'scope_type' => AuditScopeType::Datacenter->value,
        'datacenter_id' => $this->datacenter->id,
        'assignee_ids' => [$this->assignee->id],
    ]);

    $response->assertForbidden();
});

test('validation fails without required fields', function () {
    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors([
        'name',
        'due_date',
        'type',
        'scope_type',
        'datacenter_id',
        'assignee_ids',
    ]);
});

test('connection audit blocked without approved implementation file', function () {
    // Ensure the datacenter has no approved implementation files
    expect($this->datacenter->hasApprovedImplementationFiles())->toBeFalse();

    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', [
        'name' => 'Q1 2025 Connection Audit',
        'description' => 'Quarterly connection verification',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Connection->value,
        'scope_type' => AuditScopeType::Datacenter->value,
        'datacenter_id' => $this->datacenter->id,
        'assignee_ids' => [$this->assignee->id],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['type']);
    $response->assertJsonFragment([
        'type' => ['Connection audits require an approved implementation file. Please upload and approve an implementation file for this datacenter first. You can manage implementation files on the Implementation Files page.'],
    ]);

    // Now create an approved implementation file and verify the audit passes
    ImplementationFile::factory()
        ->for($this->datacenter)
        ->approved()
        ->create();

    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', [
        'name' => 'Q1 2025 Connection Audit',
        'description' => 'Quarterly connection verification',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Connection->value,
        'scope_type' => AuditScopeType::Datacenter->value,
        'datacenter_id' => $this->datacenter->id,
        'assignee_ids' => [$this->assignee->id],
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['message' => 'Validation passed']);
});

test('scope validation based on scope_type', function () {
    // Create room and rack hierarchy for testing
    $room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Test: room scope requires room_id
    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', [
        'name' => 'Room Scope Audit',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Inventory->value,
        'scope_type' => AuditScopeType::Room->value,
        'datacenter_id' => $this->datacenter->id,
        'assignee_ids' => [$this->assignee->id],
        // Missing room_id
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['room_id']);

    // Test: room scope with valid room_id passes
    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', [
        'name' => 'Room Scope Audit',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Inventory->value,
        'scope_type' => AuditScopeType::Room->value,
        'datacenter_id' => $this->datacenter->id,
        'room_id' => $room->id,
        'assignee_ids' => [$this->assignee->id],
    ]);

    $response->assertOk();

    // Test: racks scope requires rack_ids
    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', [
        'name' => 'Racks Scope Audit',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Inventory->value,
        'scope_type' => AuditScopeType::Racks->value,
        'datacenter_id' => $this->datacenter->id,
        'assignee_ids' => [$this->assignee->id],
        // Missing rack_ids
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['rack_ids']);

    // Test: racks scope with valid rack_ids passes
    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', [
        'name' => 'Racks Scope Audit',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Inventory->value,
        'scope_type' => AuditScopeType::Racks->value,
        'datacenter_id' => $this->datacenter->id,
        'rack_ids' => [$rack->id],
        'assignee_ids' => [$this->assignee->id],
    ]);

    $response->assertOk();

    // Test: racks scope with device_ids passes (optional device selection)
    $device = Device::factory()->create(['rack_id' => $rack->id]);
    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', [
        'name' => 'Racks Scope Audit with Devices',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Inventory->value,
        'scope_type' => AuditScopeType::Racks->value,
        'datacenter_id' => $this->datacenter->id,
        'rack_ids' => [$rack->id],
        'device_ids' => [$device->id],
        'assignee_ids' => [$this->assignee->id],
    ]);

    $response->assertOk();

    // Test: device_ids with datacenter scope should fail
    $response = $this->actingAs($this->itManager)->postJson('/test-audit-store', [
        'name' => 'Datacenter Scope Audit',
        'due_date' => now()->addDays(14)->toDateString(),
        'type' => AuditType::Inventory->value,
        'scope_type' => AuditScopeType::Datacenter->value,
        'datacenter_id' => $this->datacenter->id,
        'device_ids' => [$device->id],
        'assignee_ids' => [$this->assignee->id],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['device_ids']);
});

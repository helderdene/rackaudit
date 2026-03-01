<?php

use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\DeviceVerificationStatus;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Finding;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\AuditExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AuditExecutionService::class);
});

test('full inventory audit workflow from pending through all device verifications to completed', function () {
    $user = User::factory()->create();

    // Set up datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();

    // Create devices
    $device1 = Device::factory()->for($rack)->create(['name' => 'Server-01']);
    $device2 = Device::factory()->for($rack)->create(['name' => 'Server-02']);

    // Create an empty rack
    $emptyRack = Rack::factory()->for($row)->create(['name' => 'Empty-Rack']);

    // Create inventory audit
    $audit = Audit::factory()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $datacenter->id,
        'status' => AuditStatus::Pending,
    ]);

    // Prepare verification items
    $this->service->prepareDeviceVerificationItems($audit);

    // Verify initial state - audit is pending with verification items created
    expect($audit->fresh()->status)->toBe(AuditStatus::Pending);
    expect($audit->deviceVerifications()->count())->toBe(2);
    expect($audit->rackVerifications()->count())->toBe(1);

    // Get verifications
    $deviceVerification1 = $audit->deviceVerifications()->where('device_id', $device1->id)->first();
    $deviceVerification2 = $audit->deviceVerifications()->where('device_id', $device2->id)->first();
    $rackVerification = $audit->rackVerifications()->first();

    // First verification - should transition to InProgress
    $this->service->markDeviceVerified($deviceVerification1, $user, 'Device confirmed');
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::InProgress);

    // Second verification - should remain InProgress
    $this->service->markDeviceNotFound($deviceVerification2, $user, 'Device missing from rack');
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::InProgress);

    // Verify finding was created for not found device
    expect($deviceVerification2->fresh()->finding)->not->toBeNull();

    // Empty rack verification - should complete the audit
    $this->service->markEmptyRackVerified($rackVerification, $user, 'Rack confirmed empty');
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::Completed);

    // Verify final progress stats
    $stats = $this->service->getInventoryProgressStats($audit);
    expect($stats['total'])->toBe(3); // 2 devices + 1 empty rack
    expect($stats['verified'])->toBe(1);
    expect($stats['not_found'])->toBe(1);
    expect($stats['empty_racks_verified'])->toBe(1);
    expect($stats['pending'])->toBe(0);
    expect($stats['progress_percentage'])->toBe(100.00);
});

test('audit status transitions correctly via API from pending to in progress to completed', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->pending()->create([
        'type' => AuditType::Inventory,
    ]);

    // Create verifications manually (simulating pre-populated state)
    $verification1 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $verification2 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    // Verify starts as pending
    expect($audit->fresh()->status)->toBe(AuditStatus::Pending);

    // First verification via API - should transition to InProgress
    $response = $this->actingAs($user)->postJson(
        "/api/audits/{$audit->id}/device-verifications/{$verification1->id}/verify",
        ['notes' => 'First device verified']
    );

    $response->assertSuccessful();
    $response->assertJsonPath('audit_status', 'in_progress');
    expect($audit->fresh()->status)->toBe(AuditStatus::InProgress);

    // Second verification via API - should transition to Completed
    $response = $this->actingAs($user)->postJson(
        "/api/audits/{$audit->id}/device-verifications/{$verification2->id}/verify",
        ['notes' => 'Second device verified']
    );

    $response->assertSuccessful();
    $response->assertJsonPath('audit_status', 'completed');
    expect($audit->fresh()->status)->toBe(AuditStatus::Completed);
});

test('multi-operator concurrent device verification respects locking', function () {
    $operator1 = User::factory()->create(['name' => 'Operator One']);
    $operator2 = User::factory()->create(['name' => 'Operator Two']);
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
    ]);

    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    // Operator 1 locks the device
    $lockResult = $this->service->lockDevice($verification, $operator1);
    expect($lockResult)->toBeTrue();
    expect($verification->fresh()->isLockedBy($operator1))->toBeTrue();

    // Operator 2 attempts to lock - should fail
    $lockResult2 = $this->service->lockDevice($verification, $operator2);
    expect($lockResult2)->toBeFalse();

    // Operator 2 attempts to verify via API - should get 423 Locked
    $response = $this->actingAs($operator2)->postJson(
        "/api/audits/{$audit->id}/device-verifications/{$verification->id}/verify",
        ['notes' => 'Trying to verify locked device']
    );

    $response->assertStatus(423);
    $response->assertJsonPath('locked_by', 'Operator One');

    // Device should still be pending
    expect($verification->fresh()->verification_status)->toBe(DeviceVerificationStatus::Pending);

    // Operator 1 can still verify successfully
    $response = $this->actingAs($operator1)->postJson(
        "/api/audits/{$audit->id}/device-verifications/{$verification->id}/verify",
        ['notes' => 'Verified by operator who holds the lock']
    );

    $response->assertSuccessful();
    expect($verification->fresh()->verification_status)->toBe(DeviceVerificationStatus::Verified);
    expect($verification->fresh()->verified_by)->toBe($operator1->id);
});

test('empty rack verification included in progress calculation and audit completion', function () {
    $user = User::factory()->create();

    // Create hierarchy with only empty racks (no devices)
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $emptyRack1 = Rack::factory()->for($row)->create(['name' => 'Empty-Rack-A']);
    $emptyRack2 = Rack::factory()->for($row)->create(['name' => 'Empty-Rack-B']);

    $audit = Audit::factory()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $datacenter->id,
        'status' => AuditStatus::Pending,
    ]);

    // Prepare verification items - should only have empty rack verifications
    $this->service->prepareDeviceVerificationItems($audit);

    expect($audit->deviceVerifications()->count())->toBe(0);
    expect($audit->rackVerifications()->count())->toBe(2);

    // Verify initial progress
    $stats = $this->service->getInventoryProgressStats($audit);
    expect($stats['total'])->toBe(2);
    expect($stats['empty_racks_total'])->toBe(2);
    expect($stats['empty_racks_verified'])->toBe(0);
    expect($stats['progress_percentage'])->toBe(0.00);

    // Verify first empty rack - should transition to InProgress
    $rackVerification1 = $audit->rackVerifications()->where('rack_id', $emptyRack1->id)->first();
    $this->service->markEmptyRackVerified($rackVerification1, $user, 'Confirmed empty');
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::InProgress);

    // Check progress at 50%
    $stats = $this->service->getInventoryProgressStats($audit);
    expect($stats['empty_racks_verified'])->toBe(1);
    expect($stats['progress_percentage'])->toBe(50.00);

    // Verify second empty rack - should complete audit
    $rackVerification2 = $audit->rackVerifications()->where('rack_id', $emptyRack2->id)->first();
    $this->service->markEmptyRackVerified($rackVerification2, $user, 'Also confirmed empty');
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::Completed);

    // Final progress should be 100%
    $stats = $this->service->getInventoryProgressStats($audit);
    expect($stats['progress_percentage'])->toBe(100.00);
});

test('finding auto-creation has correct audit and device verification linkage', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
    ]);
    $device = Device::factory()->create(['name' => 'Misplaced-Server']);

    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->pending()
        ->create();

    // Mark as discrepant - should auto-create finding
    $notes = 'Device found at U5, documented at U10';
    $this->service->markDeviceDiscrepant($verification, $user, $notes);

    // Verify finding was created with correct linkages
    $finding = Finding::where('audit_device_verification_id', $verification->id)->first();

    expect($finding)->not->toBeNull();
    expect($finding->audit_id)->toBe($audit->id);
    expect($finding->audit_device_verification_id)->toBe($verification->id);
    expect($finding->status)->toBe(FindingStatus::Open);
    expect($finding->title)->toContain('Misplaced-Server');
    expect($finding->title)->toContain('Device discrepancy');
    expect($finding->description)->toBe($notes);

    // Verify bidirectional relationship
    $verification->refresh();
    expect($verification->finding)->not->toBeNull();
    expect($verification->finding->id)->toBe($finding->id);
});

test('filter combinations work correctly with room status and search', function () {
    $user = User::factory()->create();

    // Create datacenter with two rooms
    $datacenter = Datacenter::factory()->create();
    $room1 = Room::factory()->for($datacenter)->create(['name' => 'Room A']);
    $room2 = Room::factory()->for($datacenter)->create(['name' => 'Room B']);

    $row1 = Row::factory()->for($room1)->create();
    $row2 = Row::factory()->for($room2)->create();

    $rack1 = Rack::factory()->for($row1)->create();
    $rack2 = Rack::factory()->for($row2)->create();

    // Create devices in different rooms with different names
    $device1 = Device::factory()->for($rack1)->create([
        'name' => 'Server-Alpha',
        'asset_tag' => 'AST001',
    ]);
    $device2 = Device::factory()->for($rack1)->create([
        'name' => 'Switch-Beta',
        'asset_tag' => 'AST002',
    ]);
    $device3 = Device::factory()->for($rack2)->create([
        'name' => 'Server-Gamma',
        'asset_tag' => 'AST003',
    ]);

    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $datacenter->id,
    ]);

    // Create verifications with different statuses
    $v1 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device1)
        ->verified($user)
        ->create(['rack_id' => $rack1->id]);

    $v2 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device2)
        ->pending()
        ->create(['rack_id' => $rack1->id]);

    $v3 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device3)
        ->pending()
        ->create(['rack_id' => $rack2->id]);

    // Test: Filter by room only
    $response = $this->actingAs($user)->getJson(
        "/api/audits/{$audit->id}/device-verifications?room_id={$room1->id}"
    );
    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(2); // device1 and device2

    // Test: Filter by room + status
    $response = $this->actingAs($user)->getJson(
        "/api/audits/{$audit->id}/device-verifications?room_id={$room1->id}&verification_status=pending"
    );
    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(1); // only device2

    // Test: Filter by room + status + search
    $response = $this->actingAs($user)->getJson(
        "/api/audits/{$audit->id}/device-verifications?room_id={$room1->id}&verification_status=verified&search=Alpha"
    );
    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(1); // only device1
    expect($response->json('data.0.id'))->toBe($v1->id);

    // Test: Search across all rooms
    $response = $this->actingAs($user)->getJson(
        "/api/audits/{$audit->id}/device-verifications?search=Server"
    );
    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(2); // device1 (Server-Alpha) and device3 (Server-Gamma)
});

test('device lookup by id for QR scan flow returns correct verification', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
    ]);

    // Create a device that would be scanned via QR code
    $device = Device::factory()->create([
        'name' => 'QR-Scanned-Device',
        'asset_tag' => 'QR123',
    ]);

    // Create verification for this device
    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->pending()
        ->create();

    // Simulate QR scan lookup - search for device by ID
    $response = $this->actingAs($user)->getJson(
        "/api/audits/{$audit->id}/device-verifications?device_id={$device->id}"
    );

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.id'))->toBe($verification->id);
    expect($response->json('data.0.device.name'))->toBe('QR-Scanned-Device');
    expect($response->json('data.0.device.asset_tag'))->toBe('QR123');
});

test('room scope audit only includes devices from specified room', function () {
    $user = User::factory()->create();

    // Create datacenter with two rooms
    $datacenter = Datacenter::factory()->create();
    $targetRoom = Room::factory()->for($datacenter)->create(['name' => 'Target Room']);
    $otherRoom = Room::factory()->for($datacenter)->create(['name' => 'Other Room']);

    $targetRow = Row::factory()->for($targetRoom)->create();
    $otherRow = Row::factory()->for($otherRoom)->create();

    $targetRack = Rack::factory()->for($targetRow)->create();
    $otherRack = Rack::factory()->for($otherRow)->create();

    // Create devices in both rooms
    $targetDevice1 = Device::factory()->for($targetRack)->create(['name' => 'Target-Device-1']);
    $targetDevice2 = Device::factory()->for($targetRack)->create(['name' => 'Target-Device-2']);
    $otherDevice = Device::factory()->for($otherRack)->create(['name' => 'Other-Device']);

    // Create an empty rack in target room
    $emptyRackInTarget = Rack::factory()->for($targetRow)->create(['name' => 'Empty-Target-Rack']);

    // Create room-scoped audit
    $audit = Audit::factory()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Room,
        'datacenter_id' => $datacenter->id,
        'room_id' => $targetRoom->id,
        'status' => AuditStatus::Pending,
    ]);

    // Prepare verification items
    $this->service->prepareDeviceVerificationItems($audit);

    // Should only have devices from target room
    $deviceVerifications = $audit->deviceVerifications()->with('device')->get();
    expect($deviceVerifications)->toHaveCount(2);

    $deviceNames = $deviceVerifications->pluck('device.name')->toArray();
    expect($deviceNames)->toContain('Target-Device-1');
    expect($deviceNames)->toContain('Target-Device-2');
    expect($deviceNames)->not->toContain('Other-Device');

    // Should only have empty racks from target room
    $rackVerifications = $audit->rackVerifications()->with('rack')->get();
    expect($rackVerifications)->toHaveCount(1);
    expect($rackVerifications->first()->rack->name)->toBe('Empty-Target-Rack');

    // Verify the audit completes correctly with room-scoped items only
    $stats = $this->service->getInventoryProgressStats($audit);
    expect($stats['total'])->toBe(3); // 2 devices + 1 empty rack (all in target room)
});

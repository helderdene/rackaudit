<?php

use App\Enums\AuditStatus;
use App\Enums\DeviceVerificationStatus;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Models\AuditRackVerification;
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

test('prepareDeviceVerificationItems creates records for all devices in audit scope', function () {
    // Set up hierarchy: Datacenter -> Room -> Row -> Rack -> Device
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack1 = Rack::factory()->for($row)->create();
    $rack2 = Rack::factory()->for($row)->create();

    // Create devices in different racks
    $device1 = Device::factory()->for($rack1)->create(['name' => 'Server-01']);
    $device2 = Device::factory()->for($rack1)->create(['name' => 'Server-02']);
    $device3 = Device::factory()->for($rack2)->create(['name' => 'Switch-01']);

    // Create inventory audit with datacenter scope
    $audit = Audit::factory()
        ->inventoryType()
        ->datacenterScope()
        ->create([
            'datacenter_id' => $datacenter->id,
            'status' => AuditStatus::Pending,
        ]);

    // Prepare verification items
    $this->service->prepareDeviceVerificationItems($audit);

    // Assert verification items were created for all devices
    $verifications = $audit->deviceVerifications()->get();
    expect($verifications)->toHaveCount(3);

    // Check each device has a verification record
    $deviceIds = $verifications->pluck('device_id')->toArray();
    expect($deviceIds)->toContain($device1->id);
    expect($deviceIds)->toContain($device2->id);
    expect($deviceIds)->toContain($device3->id);

    // Check all verifications are pending
    $verifications->each(function ($verification) {
        expect($verification->verification_status)->toBe(DeviceVerificationStatus::Pending);
    });
});

test('prepareDeviceVerificationItems includes empty racks for confirmation', function () {
    // Set up hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();

    // Create a rack with devices
    $rackWithDevices = Rack::factory()->for($row)->create(['name' => 'Rack-01']);
    Device::factory()->for($rackWithDevices)->create();

    // Create an empty rack (no devices)
    $emptyRack = Rack::factory()->for($row)->create(['name' => 'Empty-Rack-01']);

    // Create inventory audit
    $audit = Audit::factory()
        ->inventoryType()
        ->datacenterScope()
        ->create([
            'datacenter_id' => $datacenter->id,
            'status' => AuditStatus::Pending,
        ]);

    // Prepare verification items
    $this->service->prepareDeviceVerificationItems($audit);

    // Assert empty rack verification was created
    $rackVerifications = $audit->rackVerifications()->get();
    expect($rackVerifications)->toHaveCount(1);
    expect($rackVerifications->first()->rack_id)->toBe($emptyRack->id);
    expect($rackVerifications->first()->verified)->toBeFalse();
});

test('prepareDeviceVerificationItems skips if verifications already exist', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();
    $device = Device::factory()->for($rack)->create();

    $audit = Audit::factory()
        ->inventoryType()
        ->datacenterScope()
        ->create([
            'datacenter_id' => $datacenter->id,
            'status' => AuditStatus::Pending,
        ]);

    // First call should create verifications
    $this->service->prepareDeviceVerificationItems($audit);
    expect($audit->deviceVerifications()->count())->toBe(1);

    // Create another device
    Device::factory()->for($rack)->create(['name' => 'New-Device']);

    // Second call should not create more verifications
    $this->service->prepareDeviceVerificationItems($audit);
    expect($audit->deviceVerifications()->count())->toBe(1);
});

test('markDeviceVerified updates status and records operator', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inventoryType()->pending()->create();

    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $this->service->markDeviceVerified($verification, $user, 'Device confirmed at location');

    $verification->refresh();

    expect($verification->verification_status)->toBe(DeviceVerificationStatus::Verified);
    expect($verification->verified_by)->toBe($user->id);
    expect($verification->verified_at)->not->toBeNull();
    expect($verification->notes)->toBe('Device confirmed at location');
});

test('markDeviceNotFound creates Finding automatically', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inventoryType()->pending()->create();
    $device = Device::factory()->create(['name' => 'Missing-Server']);

    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->pending()
        ->create();

    $this->service->markDeviceNotFound($verification, $user, 'Device not present at documented location');

    $verification->refresh();

    expect($verification->verification_status)->toBe(DeviceVerificationStatus::NotFound);
    expect($verification->verified_by)->toBe($user->id);
    expect($verification->notes)->toBe('Device not present at documented location');

    // Check that a Finding was auto-created
    $finding = Finding::where('audit_device_verification_id', $verification->id)->first();
    expect($finding)->not->toBeNull();
    expect($finding->audit_id)->toBe($audit->id);
    expect($finding->status)->toBe(FindingStatus::Open);
    expect($finding->title)->toContain('Missing-Server');
});

test('markDeviceDiscrepant creates Finding with notes', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inventoryType()->pending()->create();
    $device = Device::factory()->create(['name' => 'Wrong-Position-Server']);

    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->pending()
        ->create();

    $notes = 'Device found at U10 but documented at U15';
    $this->service->markDeviceDiscrepant($verification, $user, $notes);

    $verification->refresh();

    expect($verification->verification_status)->toBe(DeviceVerificationStatus::Discrepant);
    expect($verification->verified_by)->toBe($user->id);
    expect($verification->notes)->toBe($notes);

    // Check that a Finding was auto-created with description
    $finding = Finding::where('audit_device_verification_id', $verification->id)->first();
    expect($finding)->not->toBeNull();
    expect($finding->audit_id)->toBe($audit->id);
    expect($finding->status)->toBe(FindingStatus::Open);
    expect($finding->title)->toContain('Wrong-Position-Server');
    expect($finding->description)->toBe($notes);
});

test('bulkVerifyDevices verifies multiple pending devices', function () {
    $user = User::factory()->create();
    $lockedByUser = User::factory()->create();
    $audit = Audit::factory()->inventoryType()->inProgress()->create();

    // Create pending verifications
    $verification1 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $verification2 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    // Create a locked verification (should be skipped)
    $verification3 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->locked($lockedByUser)
        ->create();

    $verificationIds = [
        $verification1->id,
        $verification2->id,
        $verification3->id,
    ];

    $results = $this->service->bulkVerifyDevices($verificationIds, $user);

    expect($results['verified'])->toHaveCount(2);
    expect($results['skipped_locked'])->toHaveCount(1);

    // Check database state
    expect($verification1->fresh()->verification_status)->toBe(DeviceVerificationStatus::Verified);
    expect($verification2->fresh()->verification_status)->toBe(DeviceVerificationStatus::Verified);
    expect($verification3->fresh()->verification_status)->toBe(DeviceVerificationStatus::Pending);
});

test('getInventoryProgressStats returns accurate counts', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inventoryType()->inProgress()->create();

    // Create device verifications with various statuses
    // 2 pending
    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->count(2)
        ->create();

    // 3 verified
    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->verified($user)
        ->count(3)
        ->create();

    // 1 not found
    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->notFound($user)
        ->create();

    // 1 discrepant
    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->discrepant($user)
        ->create();

    // Create empty rack verifications
    // 2 pending empty racks
    AuditRackVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->count(2)
        ->create();

    // 1 verified empty rack
    AuditRackVerification::factory()
        ->forAudit($audit)
        ->verified($user)
        ->create();

    $stats = $this->service->getInventoryProgressStats($audit);

    // Device stats
    expect($stats['total_devices'])->toBe(7);
    expect($stats['verified'])->toBe(3);
    expect($stats['not_found'])->toBe(1);
    expect($stats['discrepant'])->toBe(1);
    expect($stats['pending_devices'])->toBe(2);

    // Empty rack stats
    expect($stats['empty_racks_total'])->toBe(3);
    expect($stats['empty_racks_verified'])->toBe(1);
    expect($stats['empty_racks_pending'])->toBe(2);

    // Overall stats (devices + empty racks)
    expect($stats['total'])->toBe(10); // 7 devices + 3 empty racks
    expect($stats['completed'])->toBe(6); // 3 verified + 1 not_found + 1 discrepant + 1 rack verified
    expect($stats['pending'])->toBe(4); // 2 pending devices + 2 pending racks
    expect($stats['progress_percentage'])->toBe(60.0);
});

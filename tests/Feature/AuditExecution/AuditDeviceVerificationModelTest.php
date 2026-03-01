<?php

use App\Enums\DeviceVerificationStatus;
use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('device verification model creation with required fields', function () {
    $audit = Audit::factory()->inventoryType()->create();
    $device = Device::factory()->create();

    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->create();

    expect($verification->audit_id)->toBe($audit->id);
    expect($verification->device_id)->toBe($device->id);
    expect($verification->verification_status)->toBe(DeviceVerificationStatus::Pending);
    expect($verification->verified_by)->toBeNull();
    expect($verification->verified_at)->toBeNull();
});

test('device verification status transitions correctly', function () {
    $user = User::factory()->create();
    $verification = AuditDeviceVerification::factory()->pending()->create();

    // Test transition from pending to verified
    expect($verification->verification_status)->toBe(DeviceVerificationStatus::Pending);

    $verification->markVerified($user, 'Device confirmed at location');
    expect($verification->fresh()->verification_status)->toBe(DeviceVerificationStatus::Verified);
    expect($verification->fresh()->verified_by)->toBe($user->id);
    expect($verification->fresh()->verified_at)->not->toBeNull();
    expect($verification->fresh()->notes)->toBe('Device confirmed at location');

    // Test transition from pending to not found
    $verificationNotFound = AuditDeviceVerification::factory()->pending()->create();
    $verificationNotFound->markNotFound($user, 'Device not present at rack');
    expect($verificationNotFound->fresh()->verification_status)->toBe(DeviceVerificationStatus::NotFound);
    expect($verificationNotFound->fresh()->notes)->toBe('Device not present at rack');

    // Test transition from pending to discrepant
    $verificationDiscrepant = AuditDeviceVerification::factory()->pending()->create();
    $verificationDiscrepant->markDiscrepant($user, 'Wrong position - should be U10 not U12');
    expect($verificationDiscrepant->fresh()->verification_status)->toBe(DeviceVerificationStatus::Discrepant);
    expect($verificationDiscrepant->fresh()->notes)->toBe('Wrong position - should be U10 not U12');
});

test('lock acquisition and expiration logic works correctly', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Test lock acquisition
    $verification = AuditDeviceVerification::factory()->create();
    expect($verification->isLocked())->toBeFalse();
    expect($verification->lockFor($user1))->toBeTrue();
    expect($verification->isLocked())->toBeTrue();
    expect($verification->isLockedBy($user1))->toBeTrue();

    // Test that other users cannot acquire lock
    expect($verification->lockFor($user2))->toBeFalse();
    expect($verification->isLockedBy($user2))->toBeFalse();

    // Test same user can refresh lock
    expect($verification->lockFor($user1))->toBeTrue();

    // Test unlock
    $verification->unlock();
    expect($verification->isLocked())->toBeFalse();

    // Test expired lock
    $expiredLockVerification = AuditDeviceVerification::factory()->expiredLock($user1)->create();
    expect($expiredLockVerification->isLocked())->toBeFalse();

    // Test scopes
    $freshLock = AuditDeviceVerification::factory()->locked($user1)->create();
    expect(AuditDeviceVerification::locked()->count())->toBe(1);
    expect(AuditDeviceVerification::expiredLocks()->count())->toBe(1);
});

test('relationships to audit device and verified by user work correctly', function () {
    $audit = Audit::factory()->inventoryType()->create(['name' => 'Q1 Inventory Audit']);
    $device = Device::factory()->create(['name' => 'Server-001']);
    $verifier = User::factory()->create(['name' => 'John Auditor']);

    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->verified($verifier)
        ->create();

    expect($verification->audit)->toBeInstanceOf(Audit::class);
    expect($verification->audit->id)->toBe($audit->id);
    expect($verification->audit->name)->toBe('Q1 Inventory Audit');

    expect($verification->device)->toBeInstanceOf(Device::class);
    expect($verification->device->id)->toBe($device->id);
    expect($verification->device->name)->toBe('Server-001');

    expect($verification->verifiedBy)->toBeInstanceOf(User::class);
    expect($verification->verifiedBy->id)->toBe($verifier->id);
    expect($verification->verifiedBy->name)->toBe('John Auditor');
});

test('scope queries filter verifications by status correctly', function () {
    $audit = Audit::factory()->inventoryType()->create();

    // Create verifications with different statuses
    AuditDeviceVerification::factory()->forAudit($audit)->pending()->count(3)->create();
    AuditDeviceVerification::factory()->forAudit($audit)->verified()->count(2)->create();
    AuditDeviceVerification::factory()->forAudit($audit)->notFound()->count(2)->create();
    AuditDeviceVerification::factory()->forAudit($audit)->discrepant()->count(1)->create();

    expect(AuditDeviceVerification::pending()->count())->toBe(3);
    expect(AuditDeviceVerification::verified()->count())->toBe(2);
    expect(AuditDeviceVerification::notFound()->count())->toBe(2);
    expect(AuditDeviceVerification::discrepant()->count())->toBe(1);

    // Test audit-specific counts
    expect($audit->deviceVerifications()->count())->toBe(8);
    expect($audit->totalDeviceVerifications())->toBe(8);
    expect($audit->pendingDeviceVerifications())->toBe(3);
});

test('verification status enum casts and labels correctly', function () {
    $pendingVerification = AuditDeviceVerification::factory()->pending()->create();
    $verifiedVerification = AuditDeviceVerification::factory()->verified()->create();
    $notFoundVerification = AuditDeviceVerification::factory()->notFound()->create();
    $discrepantVerification = AuditDeviceVerification::factory()->discrepant()->create();

    expect($pendingVerification->verification_status)->toBe(DeviceVerificationStatus::Pending);
    expect($pendingVerification->verification_status)->toBeInstanceOf(DeviceVerificationStatus::class);
    expect($pendingVerification->verification_status->label())->toBe('Pending');

    expect($verifiedVerification->verification_status)->toBe(DeviceVerificationStatus::Verified);
    expect($verifiedVerification->verification_status->label())->toBe('Verified');

    expect($notFoundVerification->verification_status)->toBe(DeviceVerificationStatus::NotFound);
    expect($notFoundVerification->verification_status->label())->toBe('Not Found');

    expect($discrepantVerification->verification_status)->toBe(DeviceVerificationStatus::Discrepant);
    expect($discrepantVerification->verification_status->label())->toBe('Discrepant');
});

<?php

use App\Enums\AuditStatus;
use App\Enums\DeviceVerificationStatus;
use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('GET /api/audits/{audit}/device-verifications returns paginated list with filters', function () {
    $audit = Audit::factory()->inventoryType()->inProgress()->create();
    $room = Room::factory()->create(['datacenter_id' => $audit->datacenter_id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create verifications with different statuses
    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->pending()
        ->create();

    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->verified($this->user)
        ->create();

    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->notFound($this->user)
        ->create();

    // Test basic list
    $response = $this->getJson("/api/audits/{$audit->id}/device-verifications");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'device',
                    'rack',
                    'verification_status',
                    'verification_status_label',
                    'notes',
                    'verified_by',
                    'verified_at',
                    'locked_by',
                    'locked_at',
                    'is_locked',
                ],
            ],
            'links',
            'meta',
        ]);

    expect($response->json('meta.total'))->toBe(3);

    // Test filtering by verification status
    $filteredResponse = $this->getJson("/api/audits/{$audit->id}/device-verifications?verification_status=pending");

    $filteredResponse->assertSuccessful();
    expect($filteredResponse->json('meta.total'))->toBe(1);
});

test('GET /api/audits/{audit}/device-verifications/stats returns progress statistics', function () {
    $audit = Audit::factory()->inventoryType()->inProgress()->create();

    // Create verifications with different statuses
    AuditDeviceVerification::factory()
        ->count(3)
        ->forAudit($audit)
        ->pending()
        ->create();

    AuditDeviceVerification::factory()
        ->count(2)
        ->forAudit($audit)
        ->verified($this->user)
        ->create();

    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->notFound($this->user)
        ->create();

    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->discrepant($this->user)
        ->create();

    $response = $this->getJson("/api/audits/{$audit->id}/device-verifications/stats");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'total',
                'total_devices',
                'verified',
                'not_found',
                'discrepant',
                'pending_devices',
                'empty_racks_total',
                'empty_racks_verified',
                'empty_racks_pending',
                'completed',
                'pending',
                'progress_percentage',
            ],
        ]);

    expect($response->json('data.total_devices'))->toBe(7);
    expect($response->json('data.verified'))->toBe(2);
    expect($response->json('data.not_found'))->toBe(1);
    expect($response->json('data.discrepant'))->toBe(1);
    expect($response->json('data.pending_devices'))->toBe(3);
});

test('POST /api/audits/{audit}/device-verifications/{verification}/verify marks device verified', function () {
    $audit = Audit::factory()->inventoryType()->pending()->create();
    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $response = $this->postJson(
        "/api/audits/{$audit->id}/device-verifications/{$verification->id}/verify",
        ['notes' => 'Device confirmed at location']
    );

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'id',
                'verification_status',
                'verification_status_label',
                'verified_by',
                'verified_at',
            ],
            'message',
            'audit_status',
        ]);

    expect($response->json('data.verification_status'))->toBe('verified');
    expect($response->json('message'))->toBe('Device verified successfully.');

    $verification->refresh();
    expect($verification->verification_status)->toBe(DeviceVerificationStatus::Verified);
    expect($verification->verified_by)->toBe($this->user->id);
    expect($verification->notes)->toBe('Device confirmed at location');
});

test('POST /api/audits/{audit}/device-verifications/{verification}/not-found marks device not found', function () {
    $audit = Audit::factory()->inventoryType()->pending()->create();
    $device = Device::factory()->create(['name' => 'Test Server 001']);
    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->pending()
        ->create();

    $response = $this->postJson(
        "/api/audits/{$audit->id}/device-verifications/{$verification->id}/not-found",
        ['notes' => 'Device not present at documented location']
    );

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'id',
                'verification_status',
                'verified_by',
            ],
            'message',
            'audit_status',
            'finding_id',
        ]);

    expect($response->json('data.verification_status'))->toBe('not_found');
    expect($response->json('message'))->toBe('Device marked as not found. A finding has been created.');
    expect($response->json('finding_id'))->not->toBeNull();

    $verification->refresh();
    expect($verification->verification_status)->toBe(DeviceVerificationStatus::NotFound);

    // Verify finding was created
    expect($verification->finding)->not->toBeNull();
    expect($verification->finding->title)->toContain('Device not found');
});

test('POST /api/audits/{audit}/device-verifications/{verification}/discrepant marks device discrepant', function () {
    $audit = Audit::factory()->inventoryType()->pending()->create();
    $device = Device::factory()->create(['name' => 'Test Switch 002']);
    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->pending()
        ->create();

    $response = $this->postJson(
        "/api/audits/{$audit->id}/device-verifications/{$verification->id}/discrepant",
        ['notes' => 'Device found but at wrong U position - documented U10, found at U12']
    );

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'id',
                'verification_status',
                'notes',
            ],
            'message',
            'audit_status',
            'finding_id',
        ]);

    expect($response->json('data.verification_status'))->toBe('discrepant');
    expect($response->json('message'))->toBe('Device marked as discrepant. A finding has been created.');
    expect($response->json('finding_id'))->not->toBeNull();

    $verification->refresh();
    expect($verification->verification_status)->toBe(DeviceVerificationStatus::Discrepant);
    expect($verification->notes)->toBe('Device found but at wrong U position - documented U10, found at U12');

    // Verify finding was created
    expect($verification->finding)->not->toBeNull();
    expect($verification->finding->title)->toContain('Device discrepancy');
});

test('POST /api/audits/{audit}/device-verifications/bulk-verify bulk verifies devices', function () {
    $audit = Audit::factory()->inventoryType()->pending()->create();

    // Create multiple pending verifications
    $verifications = AuditDeviceVerification::factory()
        ->count(5)
        ->forAudit($audit)
        ->pending()
        ->create();

    // Lock one verification by another user
    $otherUser = User::factory()->create();
    $lockedVerification = $verifications->last();
    $lockedVerification->lockFor($otherUser);

    $verificationIds = $verifications->pluck('id')->toArray();

    $response = $this->postJson(
        "/api/audits/{$audit->id}/device-verifications/bulk-verify",
        ['verification_ids' => $verificationIds]
    );

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'results' => [
                'verified_count',
                'verified_ids',
                'skipped_locked_count',
                'skipped_locked_ids',
            ],
            'audit_status',
        ]);

    // 4 should be verified, 1 skipped (locked)
    expect($response->json('results.verified_count'))->toBe(4);
    expect($response->json('results.skipped_locked_count'))->toBe(1);
    expect($response->json('results.skipped_locked_ids'))->toContain($lockedVerification->id);

    // Verify the locked one was not changed
    $lockedVerification->refresh();
    expect($lockedVerification->verification_status)->toBe(DeviceVerificationStatus::Pending);
});

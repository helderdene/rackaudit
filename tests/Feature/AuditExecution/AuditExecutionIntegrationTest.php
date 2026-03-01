<?php

use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\DiscrepancyType;
use App\Enums\VerificationStatus;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
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

test('complete audit lifecycle from pending through all verifications to completed status', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->pending()->create([
        'type' => AuditType::Connection,
    ]);

    // Create 3 verification items
    $verifications = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->count(3)
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    // Verify audit starts as pending
    expect($audit->status)->toBe(AuditStatus::Pending);

    // First verification - should transition to InProgress
    $this->service->markVerified($verifications[0], $user, 'First verification');
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::InProgress);

    // Second verification - should remain InProgress
    $this->service->markDiscrepant($verifications[1], $user, DiscrepancyType::Missing, 'Second is discrepant');
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::InProgress);

    // Third verification - should transition to Completed
    $this->service->markVerified($verifications[2], $user, 'Third verification');
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::Completed);

    // Verify final counts
    $stats = $this->service->getProgressStats($audit);
    expect($stats['total'])->toBe(3);
    expect($stats['verified'])->toBe(2);
    expect($stats['discrepant'])->toBe(1);
    expect($stats['pending'])->toBe(0);
    expect($stats['progress_percentage'])->toBe(100.00);
});

test('prepares verification items using datacenter scope with compareForDatacenter', function () {
    // Set up datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();

    // Create devices and ports
    $sourceDevice = Device::factory()->for($rack)->create(['name' => 'DC-Switch-01']);
    $destDevice = Device::factory()->for($rack)->create(['name' => 'DC-Server-01']);
    $sourcePort = Port::factory()->for($sourceDevice)->create(['label' => 'ge-0/0/1']);
    $destPort = Port::factory()->for($destDevice)->create(['label' => 'eth0']);

    // Create approved implementation file with expected connections
    $implementationFile = ImplementationFile::factory()
        ->for($datacenter)
        ->approved()
        ->create();

    ExpectedConnection::factory()
        ->for($implementationFile)
        ->for($sourceDevice, 'sourceDevice')
        ->for($destDevice, 'destDevice')
        ->confirmed()
        ->create([
            'source_port_id' => $sourcePort->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Create matching actual connection
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Create audit with datacenter scope (NO implementation file - uses datacenter comparison)
    $audit = Audit::factory()->create([
        'type' => AuditType::Connection,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $datacenter->id,
        'implementation_file_id' => null,
        'status' => AuditStatus::Pending,
    ]);

    // Prepare verification items - should use compareForDatacenter
    $this->service->prepareVerificationItems($audit);

    // Assert verification items were created from datacenter comparison
    $verifications = $audit->verifications()->get();
    expect($verifications)->not->toBeEmpty();
    expect($verifications->first()->comparison_status)->toBe(DiscrepancyType::Matched);
});

test('two operators cannot verify same connection simultaneously when locked', function () {
    $operator1 = User::factory()->create(['name' => 'Operator One']);
    $operator2 = User::factory()->create(['name' => 'Operator Two']);
    $audit = Audit::factory()->inProgress()->create();

    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    // Operator 1 locks the connection
    $locked1 = $this->service->lockConnection($verification, $operator1);
    expect($locked1)->toBeTrue();

    // Operator 2 attempts to lock - should fail
    $locked2 = $this->service->lockConnection($verification, $operator2);
    expect($locked2)->toBeFalse();

    // Operator 2 attempts to verify via API - should get 423 Locked
    $response = $this->actingAs($operator2)->postJson(
        "/api/audits/{$audit->id}/verifications/{$verification->id}/verify",
        ['notes' => 'Trying to verify']
    );

    $response->assertStatus(423);
    $response->assertJsonPath('locked_by', 'Operator One');

    // Verification should still be pending
    expect($verification->fresh()->verification_status)->toBe(VerificationStatus::Pending);

    // Operator 1 can still verify
    $response = $this->actingAs($operator1)->postJson(
        "/api/audits/{$audit->id}/verifications/{$verification->id}/verify",
        ['notes' => 'Successfully verified by operator 1']
    );

    $response->assertSuccessful();
    expect($verification->fresh()->verification_status)->toBe(VerificationStatus::Verified);
});

test('partial bulk verify when some connections are locked by other operator', function () {
    $operator1 = User::factory()->create();
    $operator2 = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create();

    // Create 4 matched verifications
    $v1 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $v2 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $v3 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->locked($operator2) // Locked by operator2
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $v4 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->locked($operator2) // Also locked by operator2
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    // Operator1 attempts bulk verify on all 4
    $response = $this->actingAs($operator1)->postJson(
        "/api/audits/{$audit->id}/verifications/bulk-verify",
        ['verification_ids' => [$v1->id, $v2->id, $v3->id, $v4->id]]
    );

    $response->assertSuccessful();
    $response->assertJsonPath('results.verified_count', 2);
    $response->assertJsonPath('results.skipped_locked_count', 2);

    // Verify database state
    expect($v1->fresh()->verification_status)->toBe(VerificationStatus::Verified);
    expect($v2->fresh()->verification_status)->toBe(VerificationStatus::Verified);
    expect($v3->fresh()->verification_status)->toBe(VerificationStatus::Pending);
    expect($v4->fresh()->verification_status)->toBe(VerificationStatus::Pending);
});

test('audit with implementation file uses compareForImplementationFile correctly', function () {
    // Set up hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();

    $sourceDevice = Device::factory()->for($rack)->create(['name' => 'File-Switch-01']);
    $destDevice = Device::factory()->for($rack)->create(['name' => 'File-Server-01']);
    $sourcePort = Port::factory()->for($sourceDevice)->create(['label' => 'port1']);
    $destPort = Port::factory()->for($destDevice)->create(['label' => 'port2']);

    // Create specific implementation file
    $implementationFile = ImplementationFile::factory()
        ->for($datacenter)
        ->approved()
        ->create(['original_name' => 'rack-a1-connections.csv']);

    $expectedConnection = ExpectedConnection::factory()
        ->for($implementationFile)
        ->for($sourceDevice, 'sourceDevice')
        ->for($destDevice, 'destDevice')
        ->confirmed()
        ->create([
            'source_port_id' => $sourcePort->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Create matching actual connection
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Create audit WITH implementation file (uses file-scoped comparison)
    $audit = Audit::factory()->create([
        'type' => AuditType::Connection,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $datacenter->id,
        'implementation_file_id' => $implementationFile->id,
        'status' => AuditStatus::Pending,
    ]);

    // Prepare verification items - should use compareForImplementationFile
    $this->service->prepareVerificationItems($audit);

    // Verify items were created
    $verifications = $audit->verifications()->get();
    expect($verifications)->toHaveCount(1);
    expect($verifications->first()->expected_connection_id)->toBe($expectedConnection->id);
    expect($verifications->first()->comparison_status)->toBe(DiscrepancyType::Matched);
});

test('releases expired locks for an audit', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create();

    // Create verification with expired lock
    $expiredVerification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->expiredLock($user)
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    // Create verification with fresh lock
    $freshVerification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->locked($user)
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    // Verify initial state
    expect($expiredVerification->isLocked())->toBeFalse(); // Should be detected as expired
    expect($freshVerification->isLocked())->toBeTrue();

    // Release expired locks
    $releasedCount = $this->service->releaseExpiredLocks($audit);

    expect($releasedCount)->toBe(1);

    // Verify final state
    expect($expiredVerification->fresh()->locked_by)->toBeNull();
    expect($freshVerification->fresh()->locked_by)->toBe($user->id);
});

test('verification items are not duplicated when prepareVerificationItems called twice', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();

    $sourceDevice = Device::factory()->for($rack)->create();
    $destDevice = Device::factory()->for($rack)->create();
    $sourcePort = Port::factory()->for($sourceDevice)->create();
    $destPort = Port::factory()->for($destDevice)->create();

    $implementationFile = ImplementationFile::factory()
        ->for($datacenter)
        ->approved()
        ->create();

    ExpectedConnection::factory()
        ->for($implementationFile)
        ->for($sourceDevice, 'sourceDevice')
        ->for($destDevice, 'destDevice')
        ->confirmed()
        ->create([
            'source_port_id' => $sourcePort->id,
            'dest_port_id' => $destPort->id,
        ]);

    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $audit = Audit::factory()->create([
        'type' => AuditType::Connection,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $datacenter->id,
        'implementation_file_id' => $implementationFile->id,
        'status' => AuditStatus::Pending,
    ]);

    // First call
    $this->service->prepareVerificationItems($audit);
    $countAfterFirst = $audit->verifications()->count();

    // Second call - should not create duplicates
    $this->service->prepareVerificationItems($audit);
    $countAfterSecond = $audit->verifications()->count();

    expect($countAfterFirst)->toBe($countAfterSecond);
    expect($countAfterFirst)->toBe(1);
});

test('finding is auto-created with correct discrepancy type when marking discrepant', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->pending()->create();

    // Test with different discrepancy types
    $discrepancyTypes = [
        DiscrepancyType::Missing,
        DiscrepancyType::Unexpected,
        DiscrepancyType::Mismatched,
    ];

    foreach ($discrepancyTypes as $index => $type) {
        $verification = AuditConnectionVerification::factory()
            ->forAudit($audit)
            ->pending()
            ->create([
                'comparison_status' => $type,
                'expected_connection_id' => null,
                'connection_id' => null,
            ]);

        $this->service->markDiscrepant(
            $verification,
            $user,
            $type,
            "Issue found: {$type->label()}"
        );

        $verification->refresh();

        // Verify finding was created with correct type
        expect($verification->finding)->not->toBeNull();
        expect($verification->finding->discrepancy_type)->toBe($type);
        expect($verification->finding->description)->toBe("Issue found: {$type->label()}");
    }
});

<?php

use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\DiscrepancyType;
use App\Enums\FindingStatus;
use App\Enums\VerificationStatus;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\Finding;
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

test('prepares verification items from connection comparison service for implementation file scope', function () {
    // Set up the full hierarchy: Datacenter -> Room -> Row -> Rack -> Device -> Port
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();

    // Create devices and ports
    $sourceDevice = Device::factory()->for($rack)->create(['name' => 'Switch-01']);
    $destDevice = Device::factory()->for($rack)->create(['name' => 'Server-01']);
    $sourcePort = Port::factory()->for($sourceDevice)->create(['label' => 'eth0']);
    $destPort = Port::factory()->for($destDevice)->create(['label' => 'eth1']);

    // Create implementation file with expected connections
    $implementationFile = ImplementationFile::factory()
        ->for($datacenter)
        ->approved()
        ->create();

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

    // Create audit with implementation file scope
    $audit = Audit::factory()->create([
        'type' => AuditType::Connection,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $datacenter->id,
        'implementation_file_id' => $implementationFile->id,
        'status' => AuditStatus::Pending,
    ]);

    // Prepare verification items
    $this->service->prepareVerificationItems($audit);

    // Assert verification items were created
    $verifications = $audit->verifications()->get();
    expect($verifications)->toHaveCount(1);
    expect($verifications->first()->comparison_status)->toBe(DiscrepancyType::Matched);
    expect($verifications->first()->verification_status)->toBe(VerificationStatus::Pending);
    expect($verifications->first()->expected_connection_id)->toBe($expectedConnection->id);
});

test('marks verification as verified and updates verification status', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->pending()->create();

    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create();

    $this->service->markVerified($verification, $user, 'Connection confirmed');

    $verification->refresh();

    expect($verification->verification_status)->toBe(VerificationStatus::Verified);
    expect($verification->verified_by)->toBe($user->id);
    expect($verification->verified_at)->not->toBeNull();
    expect($verification->notes)->toBe('Connection confirmed');
});

test('marks verification as discrepant and creates finding automatically', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->pending()->create();

    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->missing()
        ->pending()
        ->create();

    $this->service->markDiscrepant(
        $verification,
        $user,
        DiscrepancyType::Missing,
        'Cable not found at documented location'
    );

    $verification->refresh();

    expect($verification->verification_status)->toBe(VerificationStatus::Discrepant);
    expect($verification->discrepancy_type)->toBe(DiscrepancyType::Missing);
    expect($verification->verified_by)->toBe($user->id);
    expect($verification->notes)->toBe('Cable not found at documented location');

    // Check that a Finding was auto-created
    $finding = Finding::where('audit_connection_verification_id', $verification->id)->first();
    expect($finding)->not->toBeNull();
    expect($finding->audit_id)->toBe($audit->id);
    expect($finding->discrepancy_type)->toBe(DiscrepancyType::Missing);
    expect($finding->status)->toBe(FindingStatus::Open);
});

test('locks and unlocks connection for verification', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $audit = Audit::factory()->create();

    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    // User 1 locks the connection
    $locked = $this->service->lockConnection($verification, $user1);
    expect($locked)->toBeTrue();

    $verification->refresh();
    expect($verification->isLocked())->toBeTrue();
    expect($verification->isLockedBy($user1))->toBeTrue();

    // User 2 cannot lock same connection
    $locked2 = $this->service->lockConnection($verification, $user2);
    expect($locked2)->toBeFalse();

    // User 1 unlocks connection
    $this->service->unlockConnection($verification);

    $verification->refresh();
    expect($verification->isLocked())->toBeFalse();

    // Now user 2 can lock
    $locked3 = $this->service->lockConnection($verification, $user2);
    expect($locked3)->toBeTrue();
});

test('auto-transitions audit status from pending to in progress on first verification', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->pending()->create();

    expect($audit->status)->toBe(AuditStatus::Pending);

    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create();

    $this->service->markVerified($verification, $user);

    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::InProgress);
});

test('auto-completes audit when all connections are verified', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create();

    // Create two verifications for this audit
    $verification1 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create([
            'expected_connection_id' => null,
            'connection_id' => null,
        ]);

    $verification2 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create([
            'expected_connection_id' => null,
            'connection_id' => null,
        ]);

    // Verify first connection - audit should still be in progress
    $this->service->markVerified($verification1, $user);
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::InProgress);

    // Verify second connection - audit should auto-complete
    $this->service->markVerified($verification2, $user);
    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::Completed);
});

test('bulk verifies only matched connections and skips locked items', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create();

    // Create 3 matched verifications
    $verification1 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $verification2 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $verification3 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->locked($user2)
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    // Create 1 missing (non-matched) verification - should be skipped
    $verification4 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->missing()
        ->pending()
        ->create();

    $verificationIds = [
        $verification1->id,
        $verification2->id,
        $verification3->id,
        $verification4->id,
    ];

    $results = $this->service->bulkVerify($verificationIds, $user1);

    expect($results['verified'])->toHaveCount(2);
    expect($results['skipped_locked'])->toHaveCount(1);
    expect($results['skipped_not_matched'])->toHaveCount(1);

    // Check database state
    expect($verification1->fresh()->verification_status)->toBe(VerificationStatus::Verified);
    expect($verification2->fresh()->verification_status)->toBe(VerificationStatus::Verified);
    expect($verification3->fresh()->verification_status)->toBe(VerificationStatus::Pending);
    expect($verification4->fresh()->verification_status)->toBe(VerificationStatus::Pending);
});

test('gets progress stats for an audit', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create();

    // Create verifications with various statuses
    // 2 pending
    AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->count(2)
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    // 3 verified
    AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->verified($user)
        ->count(3)
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    // 1 discrepant
    AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->discrepant($user)
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $stats = $this->service->getProgressStats($audit);

    expect($stats['total'])->toBe(6);
    expect($stats['verified'])->toBe(3);
    expect($stats['discrepant'])->toBe(1);
    expect($stats['pending'])->toBe(2);
    expect($stats['completed'])->toBe(4);
    expect($stats['progress_percentage'])->toBe(66.67);
});

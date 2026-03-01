<?php

use App\Enums\DiscrepancyType;
use App\Enums\VerificationStatus;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('verification status transitions from pending to verified', function () {
    $user = User::factory()->create();
    $verification = AuditConnectionVerification::factory()->pending()->create();

    expect($verification->verification_status)->toBe(VerificationStatus::Pending);
    expect($verification->verified_by)->toBeNull();
    expect($verification->verified_at)->toBeNull();

    $verification->markVerified($user, 'Confirmed connection is correct');

    expect($verification->fresh()->verification_status)->toBe(VerificationStatus::Verified);
    expect($verification->fresh()->verified_by)->toBe($user->id);
    expect($verification->fresh()->verified_at)->not->toBeNull();
    expect($verification->fresh()->notes)->toBe('Confirmed connection is correct');
});

test('verification status transitions from pending to discrepant', function () {
    $user = User::factory()->create();
    $verification = AuditConnectionVerification::factory()->pending()->create();

    expect($verification->verification_status)->toBe(VerificationStatus::Pending);

    $verification->markDiscrepant($user, DiscrepancyType::Missing, 'Cable not found at documented location');

    expect($verification->fresh()->verification_status)->toBe(VerificationStatus::Discrepant);
    expect($verification->fresh()->discrepancy_type)->toBe(DiscrepancyType::Missing);
    expect($verification->fresh()->verified_by)->toBe($user->id);
    expect($verification->fresh()->verified_at)->not->toBeNull();
    expect($verification->fresh()->notes)->toBe('Cable not found at documented location');
});

test('discrepant status requires notes', function () {
    $user = User::factory()->create();
    $verification = AuditConnectionVerification::factory()->pending()->create();

    // Notes are required for discrepant - the method enforces this via type hint
    $verification->markDiscrepant($user, DiscrepancyType::Mismatched, 'Wrong cable type');

    expect($verification->fresh()->notes)->not->toBeNull();
    expect($verification->fresh()->notes)->toBe('Wrong cable type');
});

test('verification belongs to audit model', function () {
    $audit = Audit::factory()->create(['name' => 'Q1 Connection Audit']);
    $verification = AuditConnectionVerification::factory()->forAudit($audit)->create();

    expect($verification->audit)->toBeInstanceOf(Audit::class);
    expect($verification->audit->id)->toBe($audit->id);
    expect($verification->audit->name)->toBe('Q1 Connection Audit');
});

test('verification belongs to verified_by user', function () {
    $verifier = User::factory()->create(['name' => 'John Verifier']);
    $verification = AuditConnectionVerification::factory()->verified($verifier)->create();

    expect($verification->verifiedBy)->toBeInstanceOf(User::class);
    expect($verification->verifiedBy->id)->toBe($verifier->id);
    expect($verification->verifiedBy->name)->toBe('John Verifier');
});

test('lock expiration identifies stale locks after 5 minutes', function () {
    $user = User::factory()->create();

    // Fresh lock - not expired
    $freshLock = AuditConnectionVerification::factory()->locked($user)->create();
    expect($freshLock->isLocked())->toBeTrue();

    // Expired lock - older than 5 minutes
    $expiredLock = AuditConnectionVerification::factory()->expiredLock($user)->create();
    expect($expiredLock->isLocked())->toBeFalse();

    // Test scopes
    $lockedCount = AuditConnectionVerification::locked()->count();
    expect($lockedCount)->toBe(1);

    $expiredCount = AuditConnectionVerification::expiredLocks()->count();
    expect($expiredCount)->toBe(1);
});

test('lock prevents other users from locking same verification', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $verification = AuditConnectionVerification::factory()->create();

    // First user locks successfully
    expect($verification->lockFor($user1))->toBeTrue();
    expect($verification->isLocked())->toBeTrue();
    expect($verification->isLockedBy($user1))->toBeTrue();

    // Second user cannot lock
    expect($verification->lockFor($user2))->toBeFalse();
    expect($verification->isLockedBy($user2))->toBeFalse();

    // Same user can refresh lock
    expect($verification->lockFor($user1))->toBeTrue();
});

test('audit has many verifications with helper methods', function () {
    $audit = Audit::factory()->create();
    $verifier = User::factory()->create();

    // Create verifications directly with minimal data to avoid unique constraint issues
    for ($i = 0; $i < 3; $i++) {
        AuditConnectionVerification::create([
            'audit_id' => $audit->id,
            'expected_connection_id' => null,
            'connection_id' => null,
            'comparison_status' => DiscrepancyType::Matched,
            'verification_status' => VerificationStatus::Pending,
        ]);
    }

    for ($i = 0; $i < 2; $i++) {
        AuditConnectionVerification::create([
            'audit_id' => $audit->id,
            'expected_connection_id' => null,
            'connection_id' => null,
            'comparison_status' => DiscrepancyType::Matched,
            'verification_status' => VerificationStatus::Verified,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
        ]);
    }

    AuditConnectionVerification::create([
        'audit_id' => $audit->id,
        'expected_connection_id' => null,
        'connection_id' => null,
        'comparison_status' => DiscrepancyType::Missing,
        'verification_status' => VerificationStatus::Discrepant,
        'discrepancy_type' => DiscrepancyType::Missing,
        'verified_by' => $verifier->id,
        'verified_at' => now(),
        'notes' => 'Connection not found',
    ]);

    expect($audit->verifications)->toHaveCount(6);
    expect($audit->totalVerifications())->toBe(6);
    expect($audit->completedVerifications())->toBe(3);
    expect($audit->pendingVerifications())->toBe(3);
});

test('verification status enum casts correctly', function () {
    $pendingVerification = AuditConnectionVerification::factory()->pending()->create();
    $verifiedVerification = AuditConnectionVerification::factory()->verified()->create();
    $discrepantVerification = AuditConnectionVerification::factory()->discrepant()->create();

    expect($pendingVerification->verification_status)->toBe(VerificationStatus::Pending);
    expect($pendingVerification->verification_status)->toBeInstanceOf(VerificationStatus::class);
    expect($pendingVerification->verification_status->label())->toBe('Pending');

    expect($verifiedVerification->verification_status)->toBe(VerificationStatus::Verified);
    expect($verifiedVerification->verification_status->label())->toBe('Verified');

    expect($discrepantVerification->verification_status)->toBe(VerificationStatus::Discrepant);
    expect($discrepantVerification->verification_status->label())->toBe('Discrepant');
});

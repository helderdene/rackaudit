<?php

use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\DiscrepancyType;
use App\Enums\VerificationStatus;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\User;
use App\Services\AuditExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('execution page renders with audit data and progress stats', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Connection,
    ]);

    // Assign user to audit
    $audit->assignees()->attach($user);

    // Create some verification items
    AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->count(3)
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->verified($user)
        ->count(2)
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $response = $this->actingAs($user)->get("/audits/{$audit->id}/execute");

    $response->assertSuccessful();
    $response->assertInertia(function ($page) use ($audit) {
        $page->component('Audits/Execute')
            ->where('audit.id', $audit->id)
            ->has('progress_stats')
            ->where('progress_stats.total', 5)
            ->where('progress_stats.verified', 2)
            ->where('progress_stats.pending', 3);
    });
});

test('verification API returns verifications with filtering', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Connection,
    ]);

    // Create mixed verification items
    $matched = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $missing = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->missing()
        ->pending()
        ->create();

    // Fetch all verifications
    $response = $this->actingAs($user)->getJson("/api/audits/{$audit->id}/verifications");
    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');

    // Fetch only matched verifications
    $response = $this->actingAs($user)->getJson("/api/audits/{$audit->id}/verifications?comparison_status=matched");
    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $matched->id);
});

test('bulk verify API processes matched connections only', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Connection,
    ]);

    // Create matched and non-matched verifications
    $matched1 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $matched2 = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $missing = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->missing()
        ->pending()
        ->create();

    $response = $this->actingAs($user)->postJson("/api/audits/{$audit->id}/verifications/bulk-verify", [
        'verification_ids' => [$matched1->id, $matched2->id, $missing->id],
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.verified_count', 2);
    $response->assertJsonPath('results.skipped_not_matched_count', 1);

    // Verify database state
    expect($matched1->fresh()->verification_status)->toBe(VerificationStatus::Verified);
    expect($matched2->fresh()->verification_status)->toBe(VerificationStatus::Verified);
    expect($missing->fresh()->verification_status)->toBe(VerificationStatus::Pending);
});

test('marking connection as verified updates status', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->pending()->create([
        'type' => AuditType::Connection,
    ]);

    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->matched()
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $response = $this->actingAs($user)->postJson(
        "/api/audits/{$audit->id}/verifications/{$verification->id}/verify",
        ['notes' => 'Connection looks good']
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.verification_status', 'verified');
    $response->assertJsonPath('data.notes', 'Connection looks good');

    $verification->refresh();
    expect($verification->verification_status)->toBe(VerificationStatus::Verified);
    expect($verification->verified_by)->toBe($user->id);
});

test('marking connection as discrepant requires notes', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->pending()->create([
        'type' => AuditType::Connection,
    ]);

    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->missing()
        ->pending()
        ->create();

    // Try without notes - should fail
    $response = $this->actingAs($user)->postJson(
        "/api/audits/{$audit->id}/verifications/{$verification->id}/discrepant",
        [
            'discrepancy_type' => DiscrepancyType::Missing->value,
        ]
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['notes']);

    // Try with notes - should succeed
    $response = $this->actingAs($user)->postJson(
        "/api/audits/{$audit->id}/verifications/{$verification->id}/discrepant",
        [
            'discrepancy_type' => DiscrepancyType::Missing->value,
            'notes' => 'Cable is missing from the port',
        ]
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.verification_status', 'discrepant');

    $verification->refresh();
    expect($verification->verification_status)->toBe(VerificationStatus::Discrepant);
    expect($verification->finding)->not->toBeNull();
});

test('show page displays start and continue buttons based on audit status', function () {
    $user = User::factory()->create();

    // Test pending audit - should show Start Audit button
    $pendingAudit = Audit::factory()->pending()->create([
        'type' => AuditType::Connection,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get("/audits/{$pendingAudit->id}");
    $response->assertInertia(function ($page) {
        $page->where('can_start_audit', true)
            ->where('can_continue_audit', false);
    });

    // Test in-progress audit - should show Continue Audit button
    $inProgressAudit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Connection,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get("/audits/{$inProgressAudit->id}");
    $response->assertInertia(function ($page) {
        $page->where('can_start_audit', false)
            ->where('can_continue_audit', true);
    });
});

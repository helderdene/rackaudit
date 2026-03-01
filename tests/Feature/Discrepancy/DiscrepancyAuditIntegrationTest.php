<?php

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Enums\FindingStatus;
use App\Events\FindingResolved;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Discrepancy;
use App\Models\ExpectedConnection;
use App\Models\Finding;
use App\Models\Port;
use App\Models\Room;
use App\Models\User;
use App\Services\AuditExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Test 1: Importing discrepancies as verification items
 */
it('imports discrepancies as verification items into an audit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a datacenter with room
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->for($datacenter)->create();

    // Create ports for discrepancies
    $sourcePort1 = Port::factory()->create();
    $destPort1 = Port::factory()->create();
    $sourcePort2 = Port::factory()->create();
    $destPort2 = Port::factory()->create();

    // Create open discrepancies for the datacenter
    $discrepancy1 = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->forRoom($room)
        ->missing()
        ->create([
            'source_port_id' => $sourcePort1->id,
            'dest_port_id' => $destPort1->id,
        ]);

    $discrepancy2 = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->forRoom($room)
        ->unexpected()
        ->create([
            'source_port_id' => $sourcePort2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    // Create an audit for the datacenter
    $audit = Audit::factory()
        ->for($datacenter)
        ->pending()
        ->create();

    // Get the service
    $service = app(AuditExecutionService::class);

    // Import discrepancies as verification items
    $result = $service->importDiscrepanciesAsVerificationItems($audit, [$discrepancy1->id, $discrepancy2->id]);

    // Assert verification items were created
    expect($result)->toHaveCount(2);

    // Assert discrepancies are linked to the audit and have InAudit status
    $discrepancy1->refresh();
    $discrepancy2->refresh();

    expect($discrepancy1->status)->toBe(DiscrepancyStatus::InAudit);
    expect($discrepancy1->audit_id)->toBe($audit->id);
    expect($discrepancy2->status)->toBe(DiscrepancyStatus::InAudit);
    expect($discrepancy2->audit_id)->toBe($audit->id);

    // Assert verification items were created with correct data
    $verifications = $audit->verifications()->get();
    expect($verifications)->toHaveCount(2);
});

/**
 * Test 2: Checkbox selection for import (selected discrepancies only)
 */
it('only imports selected discrepancies when using checkbox selection', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();

    // Create three discrepancies
    $discrepancy1 = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->missing()
        ->create();

    $discrepancy2 = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->unexpected()
        ->create();

    $discrepancy3 = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->mismatched()
        ->create();

    $audit = Audit::factory()
        ->for($datacenter)
        ->pending()
        ->create();

    $service = app(AuditExecutionService::class);

    // Only import discrepancy 1 and 3 (simulating checkbox selection)
    $result = $service->importDiscrepanciesAsVerificationItems($audit, [$discrepancy1->id, $discrepancy3->id]);

    // Assert only selected discrepancies were imported
    expect($result)->toHaveCount(2);

    $discrepancy1->refresh();
    $discrepancy2->refresh();
    $discrepancy3->refresh();

    expect($discrepancy1->status)->toBe(DiscrepancyStatus::InAudit);
    expect($discrepancy2->status)->toBe(DiscrepancyStatus::Open); // Not imported
    expect($discrepancy3->status)->toBe(DiscrepancyStatus::InAudit);
});

/**
 * Test 3: InAudit status prevents duplicate imports
 */
it('prevents duplicate imports when discrepancy is already in audit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();

    // Create a discrepancy that's already in an audit
    $discrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->inAudit()
        ->create();

    $newAudit = Audit::factory()
        ->for($datacenter)
        ->pending()
        ->create();

    $service = app(AuditExecutionService::class);

    // Try to import the same discrepancy into a new audit
    $result = $service->importDiscrepanciesAsVerificationItems($newAudit, [$discrepancy->id]);

    // Assert no verification items were created
    expect($result)->toHaveCount(0);

    // Assert discrepancy wasn't moved to the new audit
    $discrepancy->refresh();
    expect($discrepancy->audit_id)->not->toBe($newAudit->id);
});

/**
 * Test 4: Discrepancy links to finding when verification confirms issue
 */
it('links discrepancy to finding when verification confirms the issue', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();

    // Create an expected connection that will be used for linking
    $expectedConnection = ExpectedConnection::factory()->create();

    // Create a discrepancy with expected_connection_id set
    $discrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->missing()
        ->create([
            'expected_connection_id' => $expectedConnection->id,
            'source_port_id' => $expectedConnection->source_port_id,
            'dest_port_id' => $expectedConnection->dest_port_id,
        ]);

    $audit = Audit::factory()
        ->for($datacenter)
        ->inProgress()
        ->create();

    $service = app(AuditExecutionService::class);

    // Import discrepancy
    $verifications = $service->importDiscrepanciesAsVerificationItems($audit, [$discrepancy->id]);
    expect($verifications)->toHaveCount(1);

    $verification = $verifications->first();

    // Verify the verification has the expected connection ID set
    expect($verification->expected_connection_id)->toBe($expectedConnection->id);

    // Mark the verification as discrepant (confirming the issue)
    $service->markDiscrepant($verification, $user, DiscrepancyType::Missing, 'Confirmed missing connection');

    // Assert a finding was created
    $finding = Finding::where('audit_id', $audit->id)
        ->where('audit_connection_verification_id', $verification->id)
        ->first();

    expect($finding)->not->toBeNull();
    expect($finding->discrepancy_type)->toBe(DiscrepancyType::Missing);

    // Assert discrepancy is linked to the finding
    $discrepancy->refresh();
    expect($discrepancy->finding_id)->toBe($finding->id);
});

/**
 * Test 5: Auto-resolve discrepancy when audit finding is resolved
 */
it('auto-resolves discrepancy when linked finding is resolved', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();

    // Create a discrepancy that's in audit with a linked finding
    $audit = Audit::factory()->for($datacenter)->inProgress()->create();

    $discrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->inAudit($audit)
        ->create();

    // Create a finding linked to the discrepancy
    $finding = Finding::factory()
        ->forAudit($audit)
        ->open()
        ->create();

    // Link the discrepancy to the finding
    $discrepancy->update(['finding_id' => $finding->id]);

    // Simulate finding resolution
    $finding->update([
        'status' => FindingStatus::Resolved,
        'resolved_by' => $user->id,
        'resolved_at' => now(),
    ]);

    // Dispatch the FindingResolved event
    event(new FindingResolved($finding, $user));

    // Refresh the discrepancy
    $discrepancy->refresh();

    // Assert discrepancy is now resolved
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Resolved);
    expect($discrepancy->resolved_by)->toBe($user->id);
    expect($discrepancy->resolved_at)->not->toBeNull();
});

/**
 * Test 6: Bulk status check for discrepancies (audit availability)
 */
it('can check bulk audit status for multiple discrepancies', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();

    // Create discrepancies with different statuses
    $openDiscrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->open()
        ->create();

    $inAuditDiscrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->inAudit()
        ->create();

    $resolvedDiscrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->resolved()
        ->create();

    // Call the bulk status check endpoint
    $response = $this->postJson('/api/discrepancies/bulk-status', [
        'discrepancy_ids' => [
            $openDiscrepancy->id,
            $inAuditDiscrepancy->id,
            $resolvedDiscrepancy->id,
        ],
    ]);

    $response->assertSuccessful();

    $data = $response->json('data');

    // Assert correct statuses are returned
    expect($data)->toHaveKey((string) $openDiscrepancy->id);
    expect($data[(string) $openDiscrepancy->id]['can_import'])->toBeTrue();

    expect($data)->toHaveKey((string) $inAuditDiscrepancy->id);
    expect($data[(string) $inAuditDiscrepancy->id]['can_import'])->toBeFalse();

    expect($data)->toHaveKey((string) $resolvedDiscrepancy->id);
    expect($data[(string) $resolvedDiscrepancy->id]['can_import'])->toBeFalse();
});

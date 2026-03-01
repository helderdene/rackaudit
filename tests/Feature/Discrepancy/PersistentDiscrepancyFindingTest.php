<?php

use App\Enums\DiscrepancyType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Jobs\NotifyUsersOfPersistentDiscrepancyFindings;
use App\Jobs\PromotePersistentDiscrepanciesJob;
use App\Models\Datacenter;
use App\Models\Discrepancy;
use App\Models\Finding;
use App\Models\FindingCategory;
use App\Services\PersistentDiscrepancyFindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ============================================================
// Service: PersistentDiscrepancyFindingService
// ============================================================

test('service creates findings for old open discrepancies', function () {
    $datacenter = Datacenter::factory()->create();

    $discrepancy = Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings)->toHaveCount(1);
    expect($findings->first())
        ->toBeInstanceOf(Finding::class)
        ->status->toBe(FindingStatus::Open)
        ->audit_id->toBeNull()
        ->datacenter_id->toBe($datacenter->id);

    $discrepancy->refresh();
    expect($discrepancy->finding_id)->toBe($findings->first()->id);
});

test('service skips discrepancies detected less than threshold days ago', function () {
    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(1),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings)->toBeEmpty();
});

test('service skips acknowledged discrepancies', function () {
    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->acknowledged()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings)->toBeEmpty();
});

test('service skips resolved discrepancies', function () {
    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->resolved()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings)->toBeEmpty();
});

test('service skips in-audit discrepancies', function () {
    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->inAudit()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings)->toBeEmpty();
});

test('service skips discrepancies already linked to a finding', function () {
    $datacenter = Datacenter::factory()->create();
    $existingFinding = Finding::factory()->autoCreated($datacenter)->create();

    Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
        'finding_id' => $existingFinding->id,
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings)->toBeEmpty();
});

test('service maps missing discrepancy type to high severity', function () {
    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->open()->missing()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings->first()->severity)->toBe(FindingSeverity::High);
});

test('service maps conflicting discrepancy type to high severity', function () {
    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->open()->conflicting()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings->first()->severity)->toBe(FindingSeverity::High);
});

test('service maps unexpected discrepancy type to medium severity', function () {
    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->open()->unexpected()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings->first()->severity)->toBe(FindingSeverity::Medium);
});

test('service maps mismatched discrepancy type to medium severity', function () {
    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->open()->mismatched()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings->first()->severity)->toBe(FindingSeverity::Medium);
});

test('service assigns correct category based on discrepancy type', function () {
    $datacenter = Datacenter::factory()->create();
    $category = FindingCategory::create([
        'name' => DiscrepancyType::Missing->label(),
        'description' => 'Missing items',
        'is_default' => true,
    ]);

    Discrepancy::factory()->open()->missing()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings->first()->finding_category_id)->toBe($category->id);
});

test('service sets due date based on config', function () {
    config(['discrepancies.auto_findings.due_date_days' => 14]);

    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings->first()->due_date->format('Y-m-d'))
        ->toBe(now()->addDays(14)->format('Y-m-d'));
});

test('service links finding_id back to discrepancy', function () {
    $datacenter = Datacenter::factory()->create();

    $discrepancy = Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    $discrepancy->refresh();
    expect($discrepancy->finding_id)->toBe($findings->first()->id);
});

test('service respects custom persistence threshold', function () {
    config(['discrepancies.auto_findings.persistence_threshold_days' => 7]);

    $datacenter = Datacenter::factory()->create();

    // 5 days old - under 7-day threshold
    Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $service = new PersistentDiscrepancyFindingService;
    $findings = $service->processDatacenter($datacenter);

    expect($findings)->toBeEmpty();
});

// ============================================================
// Job: PromotePersistentDiscrepanciesJob
// ============================================================

test('job dispatches notification job when findings are created', function () {
    Queue::fake([NotifyUsersOfPersistentDiscrepancyFindings::class]);

    $datacenter = Datacenter::factory()->create();

    Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $job = new PromotePersistentDiscrepanciesJob($datacenter->id);
    $job->handle(new PersistentDiscrepancyFindingService);

    Queue::assertPushed(NotifyUsersOfPersistentDiscrepancyFindings::class, function ($job) use ($datacenter) {
        return $job->datacenterId === $datacenter->id
            && $job->findings->count() === 1;
    });
});

test('job does not dispatch notification job when no findings are created', function () {
    Queue::fake([NotifyUsersOfPersistentDiscrepancyFindings::class]);

    $datacenter = Datacenter::factory()->create();

    // No persistent discrepancies
    $job = new PromotePersistentDiscrepanciesJob($datacenter->id);
    $job->handle(new PersistentDiscrepancyFindingService);

    Queue::assertNotPushed(NotifyUsersOfPersistentDiscrepancyFindings::class);
});

test('job processes all datacenters when no datacenter id is provided', function () {
    Queue::fake([NotifyUsersOfPersistentDiscrepancyFindings::class]);

    $datacenter1 = Datacenter::factory()->create();
    $datacenter2 = Datacenter::factory()->create();

    Discrepancy::factory()->open()->forDatacenter($datacenter1)->create([
        'detected_at' => now()->subDays(5),
    ]);
    Discrepancy::factory()->open()->forDatacenter($datacenter2)->create([
        'detected_at' => now()->subDays(5),
    ]);

    $job = new PromotePersistentDiscrepanciesJob;
    $job->handle(new PersistentDiscrepancyFindingService);

    Queue::assertPushed(NotifyUsersOfPersistentDiscrepancyFindings::class, function ($job) {
        return $job->findings->count() === 2;
    });
});

// ============================================================
// Config: respects enabled flag
// ============================================================

test('scope persistentBeyond returns only qualifying discrepancies', function () {
    $datacenter = Datacenter::factory()->create();

    // Should be included: Open, no finding, old enough
    $included = Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
        'finding_id' => null,
    ]);

    // Should be excluded: too recent
    Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(1),
        'finding_id' => null,
    ]);

    // Should be excluded: not open
    Discrepancy::factory()->acknowledged()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
        'finding_id' => null,
    ]);

    // Should be excluded: has finding
    $existingFinding = Finding::factory()->autoCreated($datacenter)->create();
    Discrepancy::factory()->open()->forDatacenter($datacenter)->create([
        'detected_at' => now()->subDays(5),
        'finding_id' => $existingFinding->id,
    ]);

    $results = Discrepancy::query()
        ->persistentBeyond(now()->subDays(3))
        ->forDatacenter($datacenter->id)
        ->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($included->id);
});

test('finding datacenter relationship works for auto-created findings', function () {
    $datacenter = Datacenter::factory()->create();
    $finding = Finding::factory()->autoCreated($datacenter)->create();

    expect($finding->datacenter)->not->toBeNull();
    expect($finding->datacenter->id)->toBe($datacenter->id);
    expect($finding->audit_id)->toBeNull();
});

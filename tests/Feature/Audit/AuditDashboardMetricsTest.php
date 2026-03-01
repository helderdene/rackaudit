<?php

use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user for dashboard access
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create datacenter for testing
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
});

/**
 * Test 1: Audit progress metrics (total, by status, completion percentage)
 */
test('dashboard returns correct audit progress metrics', function () {
    // Create audits with different statuses
    Audit::factory()->count(3)->pending()->create(['datacenter_id' => $this->datacenter->id]);
    Audit::factory()->count(2)->inProgress()->create(['datacenter_id' => $this->datacenter->id]);
    Audit::factory()->count(4)->completed()->create(['datacenter_id' => $this->datacenter->id]);
    Audit::factory()->count(1)->cancelled()->create(['datacenter_id' => $this->datacenter->id]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', 10)
                ->where('byStatus.pending', 3)
                ->where('byStatus.in_progress', 2)
                ->where('byStatus.completed', 4)
                ->where('byStatus.cancelled', 1)
                ->where('completionPercentage', fn ($value) => abs($value - 40.0) < 0.01)
                ->etc()
            )
        );
});

/**
 * Test 2: Audits past due date calculation
 */
test('dashboard returns correct past due audits count', function () {
    // Create audits: past due (not completed), and on-time
    Audit::factory()->pending()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->subDays(5), // Past due
    ]);
    Audit::factory()->inProgress()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->subDays(2), // Past due
    ]);
    Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->subDays(3), // Past due but completed (should not count)
    ]);
    Audit::factory()->pending()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addDays(10), // Future due date
    ]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('pastDue', 2)
                ->etc()
            )
        );
});

/**
 * Test 3: Audits due soon calculation (within 7 days)
 */
test('dashboard returns correct due soon audits count', function () {
    // Create audits with various due dates
    Audit::factory()->pending()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addDays(3), // Due soon
    ]);
    Audit::factory()->inProgress()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addDays(5), // Due soon
    ]);
    Audit::factory()->pending()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addDays(10), // Not due soon
    ]);
    Audit::factory()->pending()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->subDays(1), // Overdue (should not count as due soon)
    ]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('dueSoon', 2)
                ->etc()
            )
        );
});

/**
 * Test 4: Finding severity aggregation counts
 */
test('dashboard returns correct finding severity aggregation', function () {
    $audit = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);

    // Create findings with different severities
    Finding::factory()->count(2)->critical()->forAudit($audit)->create();
    Finding::factory()->count(3)->high()->forAudit($audit)->create();
    Finding::factory()->count(5)->medium()->forAudit($audit)->create();
    Finding::factory()->count(4)->low()->forAudit($audit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('severityMetrics', fn ($metrics) => $metrics
                ->where('critical.count', 2)
                ->has('critical.color')
                ->where('high.count', 3)
                ->has('high.color')
                ->where('medium.count', 5)
                ->has('medium.color')
                ->where('low.count', 4)
                ->has('low.color')
                ->where('total', 14)
                ->etc()
            )
        );
});

/**
 * Test 5: Resolution status metrics (open, resolved, resolution rate)
 */
test('dashboard returns correct resolution status metrics', function () {
    $audit = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);

    // Create findings with different statuses
    Finding::factory()->count(3)->open()->forAudit($audit)->create();
    Finding::factory()->count(2)->inProgress()->forAudit($audit)->create();
    Finding::factory()->count(1)->pendingReview()->forAudit($audit)->create();
    Finding::factory()->count(1)->deferred()->forAudit($audit)->create();
    Finding::factory()->count(5)->resolved()->forAudit($audit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    // Open findings: Open (3) + InProgress (2) + PendingReview (1) + Deferred (1) = 7
    // Resolved: 5
    // Total: 12
    // Resolution rate: 5/12 = ~41.67%

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('resolutionMetrics', fn ($metrics) => $metrics
                ->where('openCount', 7)
                ->where('resolvedCount', 5)
                ->where('totalCount', 12)
                ->where('resolutionRate', fn ($value) => abs($value - round((5 / 12) * 100, 2)) < 0.01)
                ->etc()
            )
        );
});

/**
 * Test 6: Average resolution time calculation using Finding::getTotalResolutionTime()
 */
test('dashboard calculates correct average resolution time', function () {
    $audit = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);

    // Create resolved findings with known resolution times
    // Finding 1: resolved 60 minutes after creation
    $finding1 = Finding::factory()->forAudit($audit)->create([
        'status' => FindingStatus::Resolved,
        'created_at' => now()->subMinutes(180),
        'resolved_at' => now()->subMinutes(120), // 60 minutes to resolve
    ]);

    // Finding 2: resolved 120 minutes after creation
    $finding2 = Finding::factory()->forAudit($audit)->create([
        'status' => FindingStatus::Resolved,
        'created_at' => now()->subMinutes(240),
        'resolved_at' => now()->subMinutes(120), // 120 minutes to resolve
    ]);

    // Finding 3: resolved 180 minutes after creation
    $finding3 = Finding::factory()->forAudit($audit)->create([
        'status' => FindingStatus::Resolved,
        'created_at' => now()->subMinutes(300),
        'resolved_at' => now()->subMinutes(120), // 180 minutes to resolve
    ]);

    // Verify individual getTotalResolutionTime() works correctly
    expect($finding1->getTotalResolutionTime())->toBe(60);
    expect($finding2->getTotalResolutionTime())->toBe(120);
    expect($finding3->getTotalResolutionTime())->toBe(180);

    // Average: (60 + 120 + 180) / 3 = 120 minutes
    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('resolutionMetrics', fn ($metrics) => $metrics
                ->where('averageResolutionTime', 120)
                ->etc()
            )
        );
});

/**
 * Test 7: Overdue findings count using Finding::scopeOverdue()
 */
test('dashboard returns correct overdue findings count', function () {
    $audit = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);

    // Create findings with different due date states
    Finding::factory()->overdue()->forAudit($audit)->create(); // Past due date
    Finding::factory()->overdue()->forAudit($audit)->create(); // Past due date
    Finding::factory()->dueSoon()->forAudit($audit)->create(); // Due soon (not overdue)
    Finding::factory()->noDueDate()->forAudit($audit)->create(); // No due date

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('resolutionMetrics', fn ($metrics) => $metrics
                ->where('overdueCount', 2)
                ->etc()
            )
        );
});

/**
 * Test 8: Metrics respect datacenter filter
 */
test('dashboard metrics respect datacenter filter', function () {
    $datacenter2 = Datacenter::factory()->create(['name' => 'Other DC']);

    // Create audits in different datacenters
    Audit::factory()->count(3)->completed()->create(['datacenter_id' => $this->datacenter->id]);
    Audit::factory()->count(5)->completed()->create(['datacenter_id' => $datacenter2->id]);

    // Create findings in different datacenters
    $audit1 = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $audit2 = Audit::factory()->create(['datacenter_id' => $datacenter2->id]);

    Finding::factory()->count(2)->critical()->forAudit($audit1)->create();
    Finding::factory()->count(10)->critical()->forAudit($audit2)->create();

    // Filter by datacenter1 only
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', 4) // 3 completed + 1 with findings
                ->etc()
            )
            ->has('severityMetrics', fn ($metrics) => $metrics
                ->where('critical.count', 2) // Only from datacenter1
                ->etc()
            )
        );
});

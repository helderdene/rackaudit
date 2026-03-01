<?php

/**
 * Tests for the Audit Dashboard metric cards UI.
 *
 * These tests verify that metric cards display correctly with proper data,
 * correct badge colors, and responsive layout for mobile/tablet views.
 */

use App\Enums\AuditStatus;
use App\Enums\FindingSeverity;
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
 * Test 1: Audit progress metrics display correctly with all status counts
 */
test('audit progress metrics section displays all status counts correctly', function () {
    // Create audits with different statuses (with due dates far in future to avoid dueSoon)
    Audit::factory()->count(5)->pending()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addMonths(3),
    ]);
    Audit::factory()->count(3)->inProgress()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addMonths(3),
    ]);
    Audit::factory()->count(8)->completed()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addMonths(3),
    ]);
    Audit::factory()->count(2)->cancelled()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addMonths(3),
    ]);

    // Create past due and due soon audits with explicit due dates
    Audit::factory()->pending()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->subDays(3), // Past due
    ]);
    Audit::factory()->inProgress()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addDays(5), // Due soon
    ]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditMetrics', fn ($metrics) => $metrics
                // Total count: 5 + 3 + 8 + 2 + 1 (past due pending) + 1 (due soon in progress) = 20
                ->where('total', 20)
                ->where('byStatus.pending', 6) // 5 + 1 past due
                ->where('byStatus.in_progress', 4) // 3 + 1 due soon
                ->where('byStatus.completed', 8)
                ->where('byStatus.cancelled', 2)
                // Completion rate: 8/20 = 40%
                ->where('completionPercentage', fn ($value) => abs($value - 40.0) < 0.01)
                ->where('pastDue', 1)
                ->where('dueSoon', 1)
            )
        );
});

/**
 * Test 2: Finding severity counts display with correct badge color classes
 */
test('finding severity metrics display with correct color classes', function () {
    $audit = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);

    // Create findings with different severities
    Finding::factory()->count(4)->critical()->forAudit($audit)->create();
    Finding::factory()->count(7)->high()->forAudit($audit)->create();
    Finding::factory()->count(12)->medium()->forAudit($audit)->create();
    Finding::factory()->count(5)->low()->forAudit($audit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('severityMetrics', fn ($metrics) => $metrics
                // Verify counts
                ->where('critical.count', 4)
                ->where('high.count', 7)
                ->where('medium.count', 12)
                ->where('low.count', 5)
                ->where('total', 28)
                // Verify color classes match FindingSeverity::color() output
                ->where('critical.color', FindingSeverity::Critical->color())
                ->where('high.color', FindingSeverity::High->color())
                ->where('medium.color', FindingSeverity::Medium->color())
                ->where('low.color', FindingSeverity::Low->color())
                // Verify labels are present
                ->where('critical.label', 'Critical')
                ->where('high.label', 'High')
                ->where('medium.label', 'Medium')
                ->where('low.label', 'Low')
            )
        );
});

/**
 * Test 3: Resolution status metrics display correctly with all fields
 */
test('resolution status metrics display open, resolved, rate, and overdue counts', function () {
    $audit = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);

    // Create findings with different statuses
    Finding::factory()->count(4)->open()->forAudit($audit)->create();
    Finding::factory()->count(3)->inProgress()->forAudit($audit)->create();
    Finding::factory()->count(2)->pendingReview()->forAudit($audit)->create();
    Finding::factory()->count(1)->deferred()->forAudit($audit)->create();
    Finding::factory()->count(10)->resolved()->forAudit($audit)->create();

    // Create overdue findings
    Finding::factory()->count(2)->overdue()->forAudit($audit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    // Open findings: Open (4) + InProgress (3) + PendingReview (2) + Deferred (1) + Overdue (2) = 12
    // Resolved: 10
    // Total: 22
    // Resolution rate: 10/22 = ~45.45%

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('resolutionMetrics', fn ($metrics) => $metrics
                ->where('openCount', 12)
                ->where('resolvedCount', 10)
                ->where('totalCount', 22)
                ->where('resolutionRate', fn ($value) => abs($value - round((10 / 22) * 100, 2)) < 0.1)
                ->where('overdueCount', 2)
                ->has('averageResolutionTime')
            )
        );
});

/**
 * Test 4: Metric cards responsive layout data is properly structured for mobile/tablet
 */
test('dashboard provides properly structured data for responsive metric card display', function () {
    // Create minimal data for structure verification
    Audit::factory()->completed()->create(['datacenter_id' => $this->datacenter->id]);
    $audit = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);
    Finding::factory()->critical()->forAudit($audit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            // Verify all metrics sections are present and properly structured
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->has('total')
                ->has('byStatus')
                ->has('byStatus.pending')
                ->has('byStatus.in_progress')
                ->has('byStatus.completed')
                ->has('byStatus.cancelled')
                ->has('completionPercentage')
                ->has('pastDue')
                ->has('dueSoon')
            )
            ->has('severityMetrics', fn ($metrics) => $metrics
                ->has('critical')
                ->has('critical.count')
                ->has('critical.color')
                ->has('critical.label')
                ->has('critical.percentage')
                ->has('high')
                ->has('medium')
                ->has('low')
                ->has('total')
            )
            ->has('resolutionMetrics', fn ($metrics) => $metrics
                ->has('openCount')
                ->has('resolvedCount')
                ->has('totalCount')
                ->has('resolutionRate')
                ->has('averageResolutionTime')
                ->has('overdueCount')
            )
        );
});

/**
 * Test 5: Empty state displays correctly when no findings exist
 */
test('metric cards display zero counts gracefully when no data exists', function () {
    // Create only audits without findings (with due dates far in future to avoid dueSoon)
    Audit::factory()->count(2)->pending()->create([
        'datacenter_id' => $this->datacenter->id,
        'due_date' => now()->addMonths(6), // Far in future to not be "due soon"
    ]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', 2)
                ->where('byStatus.pending', 2)
                ->where('byStatus.in_progress', 0)
                ->where('byStatus.completed', 0)
                ->where('byStatus.cancelled', 0)
                ->where('completionPercentage', 0)
                ->where('pastDue', 0)
                ->where('dueSoon', 0)
            )
            ->has('severityMetrics', fn ($metrics) => $metrics
                ->where('critical.count', 0)
                ->where('high.count', 0)
                ->where('medium.count', 0)
                ->where('low.count', 0)
                ->where('total', 0)
            )
            ->has('resolutionMetrics', fn ($metrics) => $metrics
                ->where('openCount', 0)
                ->where('resolvedCount', 0)
                ->where('totalCount', 0)
                ->where('resolutionRate', 0)
                ->where('overdueCount', 0)
                ->where('averageResolutionTime', null)
            )
        );
});

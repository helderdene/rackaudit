<?php

use App\Enums\AuditType;
use App\Enums\FindingSeverity;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\AuditReport;
use App\Models\Finding;
use App\Models\User;
use App\Services\AuditReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('generates report for connection audit with findings', function () {
    $audit = Audit::factory()->connectionType()->inProgress()->create();
    $generator = User::factory()->create();

    // Create findings with various severities
    Finding::factory()->forAudit($audit)->critical()->create();
    Finding::factory()->forAudit($audit)->high()->create();
    Finding::factory()->forAudit($audit)->medium()->count(2)->create();

    // Create connection verifications for comparison summary
    AuditConnectionVerification::factory()->forAudit($audit)->matched()->verified()->count(5)->create();
    AuditConnectionVerification::factory()->forAudit($audit)->missing()->verified()->count(2)->create();
    AuditConnectionVerification::factory()->forAudit($audit)->unexpected()->verified()->create();

    $service = new AuditReportService;
    $report = $service->generateReport($audit, $generator);

    expect($report)->toBeInstanceOf(AuditReport::class);
    expect($report->audit_id)->toBe($audit->id);
    expect($report->user_id)->toBe($generator->id);
    expect($report->file_path)->toContain('reports/audits/');
    expect($report->file_path)->toContain("audit-report-{$audit->id}");
    expect($report->file_size_bytes)->toBeGreaterThan(0);
    expect($report->generated_at)->not->toBeNull();

    Storage::disk('local')->assertExists($report->file_path);
});

test('generates report for inventory audit and skips connection comparison', function () {
    $audit = Audit::factory()->inventoryType()->completed()->create();
    $generator = User::factory()->create();

    Finding::factory()->forAudit($audit)->high()->count(2)->create();

    $service = new AuditReportService;
    $report = $service->generateReport($audit, $generator);

    expect($report)->toBeInstanceOf(AuditReport::class);
    expect($report->audit->type)->toBe(AuditType::Inventory);
    Storage::disk('local')->assertExists($report->file_path);
});

test('calculates executive summary metrics correctly', function () {
    $audit = Audit::factory()->connectionType()->inProgress()->create();
    $generator = User::factory()->create();

    // Create 5 findings: 2 resolved, 3 not resolved (resolution rate = 40%)
    Finding::factory()->forAudit($audit)->critical()->open()->count(2)->create();
    Finding::factory()->forAudit($audit)->high()->resolved()->create();
    Finding::factory()->forAudit($audit)->medium()->inProgress()->create();
    Finding::factory()->forAudit($audit)->low()->resolved()->create();

    $service = new AuditReportService;
    $summary = $service->calculateExecutiveSummary($audit);

    expect($summary['total_findings'])->toBe(5);
    expect($summary['resolution_rate'])->toBe(40.0);
    expect($summary['critical_count'])->toBe(2);
    expect($summary['date_range']['start'])->not->toBeNull();
});

test('groups findings by severity in correct order', function () {
    $audit = Audit::factory()->connectionType()->inProgress()->create();

    // Create findings in random order
    Finding::factory()->forAudit($audit)->low()->create(['title' => 'Low Issue']);
    Finding::factory()->forAudit($audit)->critical()->create(['title' => 'Critical Issue']);
    Finding::factory()->forAudit($audit)->medium()->create(['title' => 'Medium Issue']);
    Finding::factory()->forAudit($audit)->high()->create(['title' => 'High Issue']);

    $service = new AuditReportService;
    $groupedFindings = $service->groupFindingsBySeverity($audit);

    $severityOrder = array_keys($groupedFindings);

    expect($severityOrder)->toBe([
        FindingSeverity::Critical->value,
        FindingSeverity::High->value,
        FindingSeverity::Medium->value,
        FindingSeverity::Low->value,
    ]);
});

test('stores PDF file and creates AuditReport record', function () {
    $audit = Audit::factory()->connectionType()->inProgress()->create();
    $generator = User::factory()->create();

    Finding::factory()->forAudit($audit)->medium()->create();

    expect(AuditReport::count())->toBe(0);

    $service = new AuditReportService;
    $report = $service->generateReport($audit, $generator);

    expect(AuditReport::count())->toBe(1);

    $savedReport = AuditReport::first();
    expect($savedReport->id)->toBe($report->id);
    expect($savedReport->file_path)->toStartWith('reports/audits/audit-report-');
    expect($savedReport->file_path)->toEndWith('.pdf');

    Storage::disk('local')->assertExists($savedReport->file_path);
});

test('omits empty severity sections from grouped findings', function () {
    $audit = Audit::factory()->connectionType()->inProgress()->create();

    // Create only Critical and Low findings (no High or Medium)
    Finding::factory()->forAudit($audit)->critical()->create();
    Finding::factory()->forAudit($audit)->low()->create();

    $service = new AuditReportService;
    $groupedFindings = $service->groupFindingsBySeverity($audit);

    expect($groupedFindings)->toHaveKey(FindingSeverity::Critical->value);
    expect($groupedFindings)->toHaveKey(FindingSeverity::Low->value);
    expect($groupedFindings)->not->toHaveKey(FindingSeverity::High->value);
    expect($groupedFindings)->not->toHaveKey(FindingSeverity::Medium->value);
});

test('builds connection comparison summary for connection audits', function () {
    $audit = Audit::factory()->connectionType()->inProgress()->create();

    // Create verifications with different comparison statuses
    AuditConnectionVerification::factory()->forAudit($audit)->matched()->count(10)->create();
    AuditConnectionVerification::factory()->forAudit($audit)->missing()->count(3)->create();
    AuditConnectionVerification::factory()->forAudit($audit)->unexpected()->count(2)->create();

    $service = new AuditReportService;
    $summary = $service->buildConnectionComparisonSummary($audit);

    expect($summary['matched_count'])->toBe(10);
    expect($summary['missing_count'])->toBe(3);
    expect($summary['unexpected_count'])->toBe(2);
    expect($summary['total_count'])->toBe(15);
});

test('returns null connection comparison for inventory audits', function () {
    $audit = Audit::factory()->inventoryType()->inProgress()->create();

    $service = new AuditReportService;
    $summary = $service->buildConnectionComparisonSummary($audit);

    expect($summary)->toBeNull();
});

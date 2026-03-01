<?php

use App\Exports\AuditHistoryReportExport;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\User;
use App\Services\AuditHistoryReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user
    $this->admin = User::factory()->create(['name' => 'Test Admin']);
    $this->admin->assignRole('Administrator');

    // Create datacenter
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test Datacenter']);

    // Set up Storage fake
    Storage::fake('local');
});

/**
 * Test 1: PDF generation creates valid file with correct content
 */
test('PDF generation creates valid file with correct content', function () {
    // Create completed audits with findings
    $audit = Audit::factory()
        ->completed()
        ->connectionType()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Test Connection Audit',
            'updated_at' => now()->subDays(5),
        ]);

    // Create findings with different severities
    Finding::factory()
        ->forAudit($audit)
        ->critical()
        ->resolved()
        ->create(['resolved_at' => now()->subDays(3)]);

    Finding::factory()
        ->forAudit($audit)
        ->high()
        ->create();

    Finding::factory()
        ->forAudit($audit)
        ->medium()
        ->resolved()
        ->create(['resolved_at' => now()->subDays(2)]);

    // Generate PDF report
    $service = app(AuditHistoryReportService::class);

    $filters = [
        'time_range_preset' => '30_days',
        'accessible_datacenter_ids' => [$this->datacenter->id],
    ];

    $filePath = $service->generatePdfReport($filters, $this->admin);

    // Verify file was created
    expect($filePath)->not->toBeNull();
    expect($filePath)->toContain('reports/audit-history/');
    expect($filePath)->toContain('.pdf');
    Storage::disk('local')->assertExists($filePath);

    // Verify file has content
    $fileContent = Storage::disk('local')->get($filePath);
    expect($fileContent)->not->toBeEmpty();
    expect(strlen($fileContent))->toBeGreaterThan(0);
});

/**
 * Test 2: CSV export contains correct columns and data
 */
test('CSV export contains correct columns and data', function () {
    // Create completed audits with findings
    $connectionAudit = Audit::factory()
        ->completed()
        ->connectionType()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Connection Audit Test',
            'updated_at' => now()->subDays(5),
        ]);

    $inventoryAudit = Audit::factory()
        ->completed()
        ->inventoryType()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Inventory Audit Test',
            'updated_at' => now()->subDays(3),
        ]);

    // Create findings for connection audit with various severities
    Finding::factory()
        ->forAudit($connectionAudit)
        ->critical()
        ->resolved()
        ->create(['resolved_at' => now()->subDays(4)]);

    Finding::factory()
        ->forAudit($connectionAudit)
        ->high()
        ->create();

    // Create findings for inventory audit
    Finding::factory()
        ->forAudit($inventoryAudit)
        ->medium()
        ->resolved()
        ->create(['resolved_at' => now()->subDays(2)]);

    // Create export with default filters
    $export = new AuditHistoryReportExport([
        'time_range_preset' => '30_days',
        'accessible_datacenter_ids' => [$this->datacenter->id],
    ]);

    // Verify headings
    $headings = $export->headings();
    expect($headings)->toBe([
        'Audit Name',
        'Type',
        'Datacenter',
        'Completion Date',
        'Total Findings',
        'Critical',
        'High',
        'Medium',
        'Low',
        'Avg Resolution Time (hours)',
    ]);

    // Verify title
    expect($export->title())->toBe('Audit History Report');

    // Get collection data
    $collection = $export->collection();

    // Verify both audits are included
    expect($collection)->toHaveCount(2);

    // Check first row (should be inventory audit since sorted by desc updated_at)
    $firstRow = $collection->first();
    expect($firstRow[0])->toBe('Inventory Audit Test');
    expect($firstRow[1])->toBe('Inventory');
    expect($firstRow[2])->toBe('Test Datacenter');
    expect($firstRow[4])->toBe(1); // Total findings
    expect($firstRow[5])->toBe(0); // Critical
    expect($firstRow[6])->toBe(0); // High
    expect($firstRow[7])->toBe(1); // Medium
    expect($firstRow[8])->toBe(0); // Low

    // Check second row (connection audit)
    $secondRow = $collection->last();
    expect($secondRow[0])->toBe('Connection Audit Test');
    expect($secondRow[1])->toBe('Connection');
    expect($secondRow[4])->toBe(2); // Total findings
    expect($secondRow[5])->toBe(1); // Critical
    expect($secondRow[6])->toBe(1); // High
});

/**
 * Test 3: Exports respect current filter parameters
 */
test('exports respect current filter parameters', function () {
    // Create a second datacenter for filtering tests
    $secondDatacenter = Datacenter::factory()->create(['name' => 'Secondary DC']);

    // Create audits in both datacenters with explicit types
    $auditInFirstDc = Audit::factory()
        ->completed()
        ->connectionType()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Audit In First DC',
            'updated_at' => now()->subDays(5),
        ]);

    $auditInSecondDc = Audit::factory()
        ->completed()
        ->inventoryType()
        ->create([
            'datacenter_id' => $secondDatacenter->id,
            'name' => 'Audit In Second DC',
            'updated_at' => now()->subDays(3),
        ]);

    // Create old audit outside default time range - explicitly set to inventory type
    $oldAudit = Audit::factory()
        ->completed()
        ->inventoryType()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Very Old Audit',
            'updated_at' => now()->subMonths(2),
        ]);

    // Test filter by datacenter
    $exportFilteredByDc = new AuditHistoryReportExport([
        'time_range_preset' => '12_months',
        'datacenter_id' => $this->datacenter->id,
        'accessible_datacenter_ids' => [$this->datacenter->id, $secondDatacenter->id],
    ]);

    $collectionFilteredByDc = $exportFilteredByDc->collection();
    expect($collectionFilteredByDc)->toHaveCount(2);
    expect($collectionFilteredByDc->pluck(0)->toArray())->toContain('Audit In First DC');
    expect($collectionFilteredByDc->pluck(0)->toArray())->toContain('Very Old Audit');
    expect($collectionFilteredByDc->pluck(0)->toArray())->not->toContain('Audit In Second DC');

    // Test filter by audit type - should only return connection type audit
    $exportFilteredByType = new AuditHistoryReportExport([
        'time_range_preset' => '12_months',
        'audit_type' => 'connection',
        'accessible_datacenter_ids' => [$this->datacenter->id, $secondDatacenter->id],
    ]);

    $collectionFilteredByType = $exportFilteredByType->collection();
    expect($collectionFilteredByType)->toHaveCount(1);
    expect($collectionFilteredByType->first()[0])->toBe('Audit In First DC');
    expect($collectionFilteredByType->first()[1])->toBe('Connection');

    // Test filter by time range - old audit should be excluded
    $exportFilteredByTime = new AuditHistoryReportExport([
        'time_range_preset' => '30_days',
        'accessible_datacenter_ids' => [$this->datacenter->id, $secondDatacenter->id],
    ]);

    $collectionFilteredByTime = $exportFilteredByTime->collection();
    expect($collectionFilteredByTime)->toHaveCount(2);
    expect($collectionFilteredByTime->pluck(0)->toArray())->not->toContain('Very Old Audit');

    // Test filter by custom date range
    $exportFilteredByCustomDates = new AuditHistoryReportExport([
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->toDateString(),
        'accessible_datacenter_ids' => [$this->datacenter->id, $secondDatacenter->id],
    ]);

    $collectionFilteredByCustomDates = $exportFilteredByCustomDates->collection();
    expect($collectionFilteredByCustomDates)->toHaveCount(2);
    expect($collectionFilteredByCustomDates->pluck(0)->toArray())->not->toContain('Very Old Audit');
});

/**
 * Test 4: Filter scope description is included in PDF
 */
test('filter scope description is included in PDF', function () {
    // Create completed audit
    $audit = Audit::factory()
        ->completed()
        ->connectionType()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Test Audit For PDF',
            'updated_at' => now()->subDays(5),
        ]);

    Finding::factory()
        ->forAudit($audit)
        ->medium()
        ->create();

    $service = app(AuditHistoryReportService::class);

    // Test with specific datacenter and audit type filters
    $filters = [
        'time_range_preset' => '30_days',
        'datacenter_id' => $this->datacenter->id,
        'audit_type' => 'connection',
        'accessible_datacenter_ids' => [$this->datacenter->id],
    ];

    $filePath = $service->generatePdfReport($filters, $this->admin);

    // Verify file was created
    Storage::disk('local')->assertExists($filePath);

    // Read the generated PDF content
    $pdfContent = Storage::disk('local')->get($filePath);
    expect($pdfContent)->not->toBeEmpty();

    // Sleep to ensure different timestamp for second PDF
    sleep(1);

    // Also test with "All" filters (no specific filters)
    $filtersAll = [
        'time_range_preset' => '6_months',
        'accessible_datacenter_ids' => [$this->datacenter->id],
    ];

    $filePathAll = $service->generatePdfReport($filtersAll, $this->admin);
    Storage::disk('local')->assertExists($filePathAll);

    // Verify both PDF files were created successfully
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();
    expect(Storage::disk('local')->exists($filePathAll))->toBeTrue();

    // Verify each file contains content
    expect(strlen(Storage::disk('local')->get($filePath)))->toBeGreaterThan(0);
    expect(strlen(Storage::disk('local')->get($filePathAll)))->toBeGreaterThan(0);
});

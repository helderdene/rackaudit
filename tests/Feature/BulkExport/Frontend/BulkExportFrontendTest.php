<?php

use App\Enums\BulkImportEntityType;
use App\Models\BulkExport;
use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
    Storage::fake('local');

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create IT Manager user
    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    // Create regular viewer user
    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('Viewer');
});

/**
 * Test 1: Export index page renders with export history table
 */
test('export index page renders export history table', function () {
    // Create some exports for the admin user
    $export1 = BulkExport::factory()
        ->for($this->admin, 'user')
        ->completed()
        ->create([
            'file_name' => 'devices_export_2024.xlsx',
            'entity_type' => BulkImportEntityType::Device,
            'format' => 'xlsx',
            'total_rows' => 100,
            'processed_rows' => 100,
        ]);

    $export2 = BulkExport::factory()
        ->for($this->admin, 'user')
        ->processing()
        ->create([
            'file_name' => 'racks_export_2024.csv',
            'entity_type' => BulkImportEntityType::Rack,
            'format' => 'csv',
            'total_rows' => 50,
            'processed_rows' => 25,
        ]);

    $response = $this->actingAs($this->admin)
        ->get('/exports');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BulkExport/Index')
            ->has('exports.data', 2)
            ->has('exports.data.0', fn (Assert $export) => $export
                ->has('id')
                ->has('file_name')
                ->has('entity_type')
                ->has('entity_type_label')
                ->has('format')
                ->has('status')
                ->has('status_label')
                ->has('progress_percentage')
                ->etc()
            )
        );
});

/**
 * Test 2: Export create page renders with form and hierarchical filter options
 */
test('export create page renders with form and filter options', function () {
    // Create sample datacenter for filter options
    Datacenter::factory()->create(['name' => 'Test DC']);

    $response = $this->actingAs($this->admin)
        ->get('/exports/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BulkExport/Create')
            ->has('entityTypeOptions')
            ->has('formatOptions')
            ->has('filterOptions')
            ->has('filterOptions.datacenters')
            ->has('filterOptions.rooms')
            ->has('filterOptions.rows')
            ->has('filterOptions.racks')
        );

    // Verify entity type options are present (excludes Mixed type)
    $page = $response->viewData('page');
    $entityTypeOptions = $page['props']['entityTypeOptions'];

    expect($entityTypeOptions)->toBeArray();
    expect(count($entityTypeOptions))->toBeGreaterThan(0);

    // Check that common entity types are present and Mixed is excluded
    $values = collect($entityTypeOptions)->pluck('value')->toArray();
    expect($values)->toContain('datacenter');
    expect($values)->toContain('device');
    expect($values)->not->toContain('mixed');
});

/**
 * Test 3: Export show page displays status and progress (for polling)
 */
test('export show page displays status and progress for polling', function () {
    $export = BulkExport::factory()
        ->for($this->admin, 'user')
        ->processing()
        ->create([
            'entity_type' => BulkImportEntityType::Device,
            'format' => 'xlsx',
            'total_rows' => 100,
            'processed_rows' => 45,
            'filters' => ['datacenter_id' => 1],
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/exports/{$export->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BulkExport/Show')
            ->has('export', fn (Assert $exportData) => $exportData
                ->where('id', $export->id)
                ->where('status', 'processing')
                ->where('status_label', 'Processing')
                ->where('total_rows', 100)
                ->where('processed_rows', 45)
                ->has('progress_percentage')
                ->etc()
            )
        );
});

/**
 * Test 4: Download button appears for completed exports
 */
test('download button appears when export is completed', function () {
    // Create the export file
    $filePath = 'exports/test-uuid_devices.xlsx';
    Storage::disk('local')->put($filePath, 'test content');

    $export = BulkExport::factory()
        ->for($this->admin, 'user')
        ->completed()
        ->create([
            'entity_type' => BulkImportEntityType::Device,
            'format' => 'xlsx',
            'file_path' => $filePath,
            'file_name' => 'devices_export.xlsx',
            'total_rows' => 100,
            'processed_rows' => 100,
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/exports/{$export->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('export.status', 'completed')
            ->has('export.download_url')
        );

    // Verify download works
    $downloadResponse = $this->actingAs($this->admin)
        ->get("/exports/{$export->id}/download");

    $downloadResponse->assertOk();
});

/**
 * Test 5: Export show page returns JSON for AJAX polling requests
 */
test('export show returns JSON for AJAX polling requests', function () {
    $export = BulkExport::factory()
        ->for($this->admin, 'user')
        ->processing()
        ->create([
            'entity_type' => BulkImportEntityType::Device,
            'format' => 'xlsx',
            'total_rows' => 100,
            'processed_rows' => 60,
        ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/exports/{$export->id}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $export->id,
                'status' => 'processing',
                'total_rows' => 100,
                'processed_rows' => 60,
            ],
        ]);
});

/**
 * Test 6: Export pages are only accessible to authorized roles
 */
test('export pages accessible for authorized roles only', function () {
    // Admin can access exports index
    $response = $this->actingAs($this->admin)
        ->get('/exports');
    $response->assertOk();

    // IT Manager can access exports index
    $response = $this->actingAs($this->itManager)
        ->get('/exports');
    $response->assertOk();

    // Viewer cannot access exports
    $response = $this->actingAs($this->viewer)
        ->get('/exports');
    $response->assertForbidden();

    // Viewer cannot access create page
    $response = $this->actingAs($this->viewer)
        ->get('/exports/create');
    $response->assertForbidden();
});

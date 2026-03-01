<?php

use App\Enums\BulkImportEntityType;
use App\Enums\BulkImportStatus;
use App\Models\BulkImport;
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
 * Test 1: Import page renders with file upload section
 */
test('import create page renders with file upload section', function () {
    $response = $this->actingAs($this->admin)
        ->get('/imports/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BulkImport/Create')
            ->has('entityTypeOptions')
            ->has('maxFileSizeMB')
        );
});

/**
 * Test 2: Template download buttons are present in the create page
 */
test('create page includes entity type options for templates', function () {
    $response = $this->actingAs($this->admin)
        ->get('/imports/create');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('BulkImport/Create')
        ->has('entityTypeOptions', fn (Assert $options) => $options
            ->each(fn (Assert $option) => $option
                ->has('value')
                ->has('label')
            )
        )
    );

    // Verify entity type options are present
    $page = $response->viewData('page');
    $entityTypeOptions = $page['props']['entityTypeOptions'];

    expect($entityTypeOptions)->toBeArray();
    expect(count($entityTypeOptions))->toBeGreaterThan(0);

    // Check that common entity types are present
    $values = collect($entityTypeOptions)->pluck('value')->toArray();
    expect($values)->toContain('datacenter');
    expect($values)->toContain('device');
});

/**
 * Test 3: Import index page displays import history table
 */
test('import index page displays import history', function () {
    // Create some imports for the admin user
    $import1 = BulkImport::factory()
        ->for($this->admin, 'user')
        ->completed()
        ->create([
            'file_name' => 'test_import_1.csv',
            'success_count' => 10,
            'failure_count' => 2,
        ]);

    $import2 = BulkImport::factory()
        ->for($this->admin, 'user')
        ->processing()
        ->create([
            'file_name' => 'test_import_2.xlsx',
            'total_rows' => 50,
            'processed_rows' => 25,
        ]);

    $response = $this->actingAs($this->admin)
        ->get('/imports');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BulkImport/Index')
            ->has('imports.data', 2)
            ->has('imports.data.0', fn (Assert $import) => $import
                ->has('id')
                ->has('file_name')
                ->has('status')
                ->has('status_label')
                ->etc()
            )
        );
});

/**
 * Test 4: Import show page displays progress and error summary
 */
test('import show page displays progress information', function () {
    $bulkImport = BulkImport::factory()
        ->for($this->admin, 'user')
        ->completed()
        ->create([
            'total_rows' => 100,
            'processed_rows' => 100,
            'success_count' => 95,
            'failure_count' => 5,
            'error_report_path' => 'import-errors/test_errors.csv',
        ]);

    // Create the error report file
    Storage::disk('local')->put('import-errors/test_errors.csv', "row_number,field_name,error_message\n2,name,Required\n");

    $response = $this->actingAs($this->admin)
        ->get("/imports/{$bulkImport->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BulkImport/Show')
            ->has('import', fn (Assert $import) => $import
                ->where('id', $bulkImport->id)
                ->where('status', 'completed')
                ->where('total_rows', 100)
                ->where('processed_rows', 100)
                ->where('success_count', 95)
                ->where('failure_count', 5)
                ->where('has_error_report', true)
                ->etc()
            )
        );
});

/**
 * Test 5: Error report download button appears when errors exist
 */
test('error report is accessible when import has failures', function () {
    // Create error report content
    $errorContent = "row_number,field_name,error_message\n";
    $errorContent .= "2,name,The name field is required.\n";
    Storage::disk('local')->put('import-errors/test_errors.csv', $errorContent);

    $bulkImport = BulkImport::factory()
        ->for($this->admin, 'user')
        ->completed()
        ->create([
            'failure_count' => 1,
            'error_report_path' => 'import-errors/test_errors.csv',
        ]);

    // Verify has_error_report is true in the response
    $response = $this->actingAs($this->admin)
        ->get("/imports/{$bulkImport->id}");

    $response->assertInertia(fn (Assert $page) => $page
        ->where('import.has_error_report', true)
        ->where('import.has_errors', true)
    );

    // Verify the error download endpoint works
    $downloadResponse = $this->actingAs($this->admin)
        ->get("/imports/{$bulkImport->id}/errors");

    $downloadResponse->assertOk();
});

/**
 * Test 6: Imports navigation is visible for Admin and IT Manager roles
 */
test('imports navigation accessible for authorized roles only', function () {
    // Admin can access imports index
    $response = $this->actingAs($this->admin)
        ->get('/imports');
    $response->assertOk();

    // IT Manager can access imports index
    $response = $this->actingAs($this->itManager)
        ->get('/imports');
    $response->assertOk();

    // Viewer cannot access imports
    $response = $this->actingAs($this->viewer)
        ->get('/imports');
    $response->assertForbidden();

    // Viewer cannot access create page
    $response = $this->actingAs($this->viewer)
        ->get('/imports/create');
    $response->assertForbidden();
});

<?php

use App\Models\BulkExport;
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
 * Test 1: Index returns paginated export history
 */
test('index returns paginated export history', function () {
    // Create exports for the admin user
    BulkExport::factory()
        ->count(3)
        ->for($this->admin, 'user')
        ->create();

    // Test JSON response
    $response = $this->actingAs($this->admin)
        ->getJson('/exports');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'entity_type',
                    'entity_type_label',
                    'format',
                    'file_name',
                    'status',
                    'status_label',
                    'total_rows',
                    'processed_rows',
                    'progress_percentage',
                    'created_at',
                ],
            ],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

/**
 * Test 2: Create returns form with entity type and format options
 * Tests Inertia response props without checking if Vue component file exists.
 */
test('create returns form with entity type and format options', function () {
    $response = $this->actingAs($this->admin)
        ->get('/exports/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BulkExport/Create', shouldExist: false)
            ->has('entityTypeOptions')
            ->has('formatOptions')
            ->has('filterOptions.datacenters')
        );
});

/**
 * Test 3: Store initiates export and redirects to show
 */
test('store initiates export and redirects to show', function () {
    $response = $this->actingAs($this->admin)
        ->post('/exports', [
            'entity_type' => 'datacenter',
            'format' => 'xlsx',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('bulk_exports', [
        'user_id' => $this->admin->id,
        'entity_type' => 'datacenter',
        'format' => 'xlsx',
    ]);
});

/**
 * Test 4: Show returns export status with progress
 */
test('show returns export status with progress', function () {
    $bulkExport = BulkExport::factory()
        ->for($this->admin, 'user')
        ->processing()
        ->create([
            'total_rows' => 100,
            'processed_rows' => 50,
        ]);

    // Test JSON response for API polling
    $response = $this->actingAs($this->admin)
        ->getJson("/exports/{$bulkExport->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $bulkExport->id)
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.total_rows', 100)
        ->assertJsonPath('data.processed_rows', 50);

    // Verify progress percentage
    $data = $response->json('data');
    expect((float) $data['progress_percentage'])->toBe(50.0);
});

/**
 * Test 5: Download returns file stream for completed exports
 */
test('download returns file stream for completed exports', function () {
    $fileContent = "name,address_line_1,city\nTest DC,123 Main St,New York\n";
    Storage::disk('local')->put('exports/test_export.xlsx', $fileContent);

    $bulkExport = BulkExport::factory()
        ->for($this->admin, 'user')
        ->completed()
        ->create([
            'file_path' => 'exports/test_export.xlsx',
            'file_name' => 'test_export.xlsx',
            'format' => 'xlsx',
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/exports/{$bulkExport->id}/download");

    $response->assertOk();
    $response->assertDownload();
});

/**
 * Test 6: Unauthorized users receive 403 response
 */
test('unauthorized users receive 403 response', function () {
    // Viewer cannot access exports index
    $response = $this->actingAs($this->viewer)
        ->getJson('/exports');
    $response->assertForbidden();

    // Viewer cannot access create page
    $response = $this->actingAs($this->viewer)
        ->get('/exports/create');
    $response->assertForbidden();

    // Viewer cannot store exports
    $response = $this->actingAs($this->viewer)
        ->postJson('/exports', [
            'entity_type' => 'datacenter',
            'format' => 'xlsx',
        ]);
    $response->assertForbidden();
});

/**
 * Test 7: Only export owner or Administrator can access export
 */
test('only export owner or Administrator can access export', function () {
    // Create export owned by IT Manager
    $bulkExport = BulkExport::factory()
        ->for($this->itManager, 'user')
        ->completed()
        ->create();

    // IT Manager can view their own export
    $response = $this->actingAs($this->itManager)
        ->getJson("/exports/{$bulkExport->id}");
    $response->assertOk();

    // Admin can view any export
    $response = $this->actingAs($this->admin)
        ->getJson("/exports/{$bulkExport->id}");
    $response->assertOk();

    // Create another IT Manager who should not access others' exports
    $otherItManager = User::factory()->create();
    $otherItManager->assignRole('IT Manager');

    $response = $this->actingAs($otherItManager)
        ->getJson("/exports/{$bulkExport->id}");
    $response->assertForbidden();
});

/**
 * Test 8: Download returns 404 for non-completed exports
 */
test('download returns 404 for non-completed exports', function () {
    $bulkExport = BulkExport::factory()
        ->for($this->admin, 'user')
        ->processing()
        ->create();

    $response = $this->actingAs($this->admin)
        ->getJson("/exports/{$bulkExport->id}/download");

    $response->assertNotFound();
});

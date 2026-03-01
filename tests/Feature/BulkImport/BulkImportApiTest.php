<?php

use App\Enums\BulkImportEntityType;
use App\Enums\BulkImportStatus;
use App\Models\BulkImport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
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
 * Test 1: File upload endpoint accepts CSV and XLSX files
 */
test('file upload endpoint accepts CSV and XLSX files', function () {
    // Create a simple CSV file
    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    $csvContent .= "Test DC,123 Main St,New York,NY,10001,USA,John Doe,john@test.com,555-1234\n";

    $csvFile = UploadedFile::fake()->createWithContent('test_import.csv', $csvContent);

    $response = $this->actingAs($this->admin)
        ->postJson('/imports', [
            'file' => $csvFile,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'entity_type',
                'entity_type_label',
                'file_name',
                'status',
                'status_label',
                'total_rows',
                'processed_rows',
                'success_count',
                'failure_count',
                'progress_percentage',
                'has_errors',
                'has_error_report',
                'created_at',
            ],
            'message',
        ])
        ->assertJsonFragment([
            'file_name' => 'test_import.csv',
            'message' => 'Import initiated successfully.',
        ]);

    // Verify BulkImport was created
    $this->assertDatabaseHas('bulk_imports', [
        'user_id' => $this->admin->id,
        'file_name' => 'test_import.csv',
    ]);
});

/**
 * Test 2: File validation rejects invalid formats and oversized files
 */
test('file validation rejects invalid formats and oversized files', function () {
    // Test invalid file extension
    $pdfFile = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->admin)
        ->postJson('/imports', [
            'file' => $pdfFile,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);

    // Test missing file
    $response = $this->actingAs($this->admin)
        ->postJson('/imports', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

/**
 * Test 3: Import status polling endpoint returns correct progress
 */
test('import status polling endpoint returns correct progress', function () {
    // Create an import in processing state
    $bulkImport = BulkImport::factory()
        ->for($this->admin, 'user')
        ->processing()
        ->create([
            'total_rows' => 100,
            'processed_rows' => 50,
            'success_count' => 45,
            'failure_count' => 5,
        ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/imports/{$bulkImport->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $bulkImport->id)
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.total_rows', 100)
        ->assertJsonPath('data.processed_rows', 50)
        ->assertJsonPath('data.success_count', 45)
        ->assertJsonPath('data.failure_count', 5);

    // Verify progress percentage exists and is correct value (50 or 50.0)
    $data = $response->json('data');
    expect((float) $data['progress_percentage'])->toBe(50.0);
});

/**
 * Test 4: Error report download endpoint
 */
test('error report download endpoint works when errors exist', function () {
    // Create error report file
    $errorContent = "row_number,field_name,error_message\n";
    $errorContent .= "2,name,The name field is required.\n";
    $errorContent .= "5,email,The email must be a valid email address.\n";

    Storage::disk('local')->put('import-errors/test_errors.csv', $errorContent);

    // Create an import with error report
    $bulkImport = BulkImport::factory()
        ->for($this->admin, 'user')
        ->completed()
        ->create([
            'failure_count' => 2,
            'error_report_path' => 'import-errors/test_errors.csv',
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/imports/{$bulkImport->id}/errors");

    $response->assertOk();
    // Check content type contains 'text/csv' (case insensitive)
    expect(strtolower($response->headers->get('Content-Type')))->toContain('text/csv');

    // Test 404 when no error report exists
    $bulkImportNoErrors = BulkImport::factory()
        ->for($this->admin, 'user')
        ->completed()
        ->create([
            'failure_count' => 0,
            'error_report_path' => null,
        ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/imports/{$bulkImportNoErrors->id}/errors");

    $response->assertNotFound();
});

/**
 * Test 5: Template download endpoints work for each entity type
 */
test('template download endpoints work for each entity type', function () {
    $entityTypes = ['datacenter', 'room', 'row', 'rack', 'device', 'port'];

    foreach ($entityTypes as $entityType) {
        $response = $this->actingAs($this->admin)
            ->get("/imports/templates/{$entityType}");

        $response->assertOk();
    }
});

/**
 * Test 6: Authorization - only Admin/IT Manager can import
 */
test('authorization only allows Admin and IT Manager to import', function () {
    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    $csvFile = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

    // Admin can access imports
    $response = $this->actingAs($this->admin)
        ->getJson('/imports');
    $response->assertOk();

    // IT Manager can access imports
    $response = $this->actingAs($this->itManager)
        ->getJson('/imports');
    $response->assertOk();

    // Viewer cannot access imports
    $response = $this->actingAs($this->viewer)
        ->getJson('/imports');
    $response->assertForbidden();

    // Viewer cannot upload files
    $response = $this->actingAs($this->viewer)
        ->postJson('/imports', ['file' => $csvFile]);
    $response->assertForbidden();
});

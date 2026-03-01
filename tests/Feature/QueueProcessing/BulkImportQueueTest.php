<?php

use App\Enums\BulkImportEntityType;
use App\Enums\BulkImportStatus;
use App\Jobs\ProcessBulkImportJob;
use App\Models\BulkImport;
use App\Models\Datacenter;
use App\Models\User;
use App\Services\BulkImportService;
use App\Services\ImportErrorReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('ProcessBulkImportJob dispatches for 100+ row imports', function () {
    Queue::fake();

    $user = User::factory()->create();

    // Create a CSV with 150 rows (header + 150 data rows)
    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    for ($i = 1; $i <= 150; $i++) {
        $csvContent .= "Datacenter $i,123 Main St,City $i,State,1000$i,USA,Contact $i,contact$i@example.com,555-000-$i\n";
    }

    Storage::disk('local')->put('imports/test_large.csv', $csvContent);

    $bulkImportService = app(BulkImportService::class);
    $bulkImport = $bulkImportService->initiateImport(
        $user,
        'imports/test_large.csv',
        'test_large.csv',
        BulkImportEntityType::Datacenter
    );

    Queue::assertPushed(ProcessBulkImportJob::class, function ($job) use ($bulkImport) {
        return $job->bulkImport->id === $bulkImport->id;
    });
});

test('sync processing occurs for under 100 row imports', function () {
    Queue::fake();

    $user = User::factory()->create();

    // Create a CSV with 50 rows
    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    for ($i = 1; $i <= 50; $i++) {
        $csvContent .= "Datacenter $i,123 Main St,City $i,State,1000$i,USA,Contact $i,contact$i@example.com,555-000-$i\n";
    }

    Storage::disk('local')->put('imports/test_small.csv', $csvContent);

    $bulkImportService = app(BulkImportService::class);
    $bulkImport = $bulkImportService->initiateImport(
        $user,
        'imports/test_small.csv',
        'test_small.csv',
        BulkImportEntityType::Datacenter
    );

    // Job should NOT be dispatched for small imports
    Queue::assertNotPushed(ProcessBulkImportJob::class);

    // Import should be completed synchronously
    expect($bulkImport->fresh()->status)->toBe(BulkImportStatus::Completed);
    expect($bulkImport->fresh()->processed_rows)->toBe(50);
});

test('progress updates stored correctly during processing', function () {
    $user = User::factory()->create();

    // Create a CSV with 200 rows
    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    for ($i = 1; $i <= 200; $i++) {
        $csvContent .= "Datacenter $i,123 Main St,City $i,State,1000$i,USA,Contact $i,contact$i@example.com,555-000-$i\n";
    }

    Storage::disk('local')->put('imports/test_progress.csv', $csvContent);

    $bulkImport = BulkImport::factory()->create([
        'user_id' => $user->id,
        'entity_type' => BulkImportEntityType::Datacenter,
        'file_name' => 'test_progress.csv',
        'file_path' => 'imports/test_progress.csv',
        'status' => BulkImportStatus::Pending,
        'total_rows' => 200,
        'processed_rows' => 0,
    ]);

    // Execute the job
    $job = new ProcessBulkImportJob($bulkImport);
    $job->handle();

    $bulkImport->refresh();

    expect($bulkImport->status)->toBe(BulkImportStatus::Completed);
    expect($bulkImport->processed_rows)->toBe(200);
    expect($bulkImport->success_count)->toBe(200);
    expect($bulkImport->failure_count)->toBe(0);
    expect($bulkImport->started_at)->not->toBeNull();
    expect($bulkImport->completed_at)->not->toBeNull();
});

test('error report CSV generation on failures', function () {
    $user = User::factory()->create();

    // Create a CSV with some invalid rows (missing required fields)
    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    // Valid row
    $csvContent .= "Valid DC,123 Main St,New York,NY,10001,USA,John Doe,john@example.com,555-123-4567\n";
    // Invalid row - missing name
    $csvContent .= ",123 Main St,Boston,MA,02101,USA,Jane Doe,jane@example.com,555-987-6543\n";
    // Invalid row - invalid email
    $csvContent .= "Another DC,456 Oak Ave,Chicago,IL,60601,USA,Bob Smith,invalid-email,555-555-5555\n";
    // Valid row
    $csvContent .= "Third DC,789 Pine Rd,Seattle,WA,98101,USA,Alice Johnson,alice@example.com,555-111-2222\n";

    Storage::disk('local')->put('imports/test_errors.csv', $csvContent);

    $bulkImport = BulkImport::factory()->create([
        'user_id' => $user->id,
        'entity_type' => BulkImportEntityType::Datacenter,
        'file_name' => 'test_errors.csv',
        'file_path' => 'imports/test_errors.csv',
        'status' => BulkImportStatus::Pending,
        'total_rows' => 4,
        'processed_rows' => 0,
    ]);

    $job = new ProcessBulkImportJob($bulkImport);
    $job->handle();

    $bulkImport->refresh();

    expect($bulkImport->success_count)->toBe(2);
    expect($bulkImport->failure_count)->toBe(2);
    expect($bulkImport->error_report_path)->not->toBeNull();

    // Verify error report exists and has correct format
    expect(Storage::disk('local')->exists($bulkImport->error_report_path))->toBeTrue();

    $errorReport = Storage::disk('local')->get($bulkImport->error_report_path);
    $lines = explode("\n", trim($errorReport));

    // Should have header + error lines
    expect(count($lines))->toBeGreaterThanOrEqual(3); // header + at least 2 error lines
    expect($lines[0])->toBe('row_number,field_name,error_message');
});

test('BulkImport status transitions through job lifecycle', function () {
    $user = User::factory()->create();

    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    $csvContent .= "Test DC,123 Main St,New York,NY,10001,USA,John Doe,john@example.com,555-123-4567\n";

    Storage::disk('local')->put('imports/test_lifecycle.csv', $csvContent);

    $bulkImport = BulkImport::factory()->create([
        'user_id' => $user->id,
        'entity_type' => BulkImportEntityType::Datacenter,
        'file_name' => 'test_lifecycle.csv',
        'file_path' => 'imports/test_lifecycle.csv',
        'status' => BulkImportStatus::Pending,
        'total_rows' => 1,
        'processed_rows' => 0,
        'started_at' => null,
        'completed_at' => null,
    ]);

    // Initial state
    expect($bulkImport->status)->toBe(BulkImportStatus::Pending);
    expect($bulkImport->started_at)->toBeNull();

    // Execute job
    $job = new ProcessBulkImportJob($bulkImport);
    $job->handle();

    $bulkImport->refresh();

    // Final state
    expect($bulkImport->status)->toBe(BulkImportStatus::Completed);
    expect($bulkImport->started_at)->not->toBeNull();
    expect($bulkImport->completed_at)->not->toBeNull();
    expect($bulkImport->processed_rows)->toBe(1);
});

test('partial import succeeds with valid rows and fails invalid rows', function () {
    $user = User::factory()->create();

    // Create CSV with mix of valid and invalid rows
    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    // Valid
    $csvContent .= "DC One,100 First St,New York,NY,10001,USA,Contact One,one@example.com,555-001-0001\n";
    // Valid
    $csvContent .= "DC Two,200 Second St,Boston,MA,02101,USA,Contact Two,two@example.com,555-002-0002\n";
    // Invalid - missing required name
    $csvContent .= ",300 Third St,Chicago,IL,60601,USA,Contact Three,three@example.com,555-003-0003\n";
    // Valid
    $csvContent .= "DC Four,400 Fourth St,Seattle,WA,98101,USA,Contact Four,four@example.com,555-004-0004\n";
    // Invalid - missing required city
    $csvContent .= "DC Five,500 Fifth St,,CA,94101,USA,Contact Five,five@example.com,555-005-0005\n";

    Storage::disk('local')->put('imports/test_partial.csv', $csvContent);

    $bulkImport = BulkImport::factory()->create([
        'user_id' => $user->id,
        'entity_type' => BulkImportEntityType::Datacenter,
        'file_name' => 'test_partial.csv',
        'file_path' => 'imports/test_partial.csv',
        'status' => BulkImportStatus::Pending,
        'total_rows' => 5,
        'processed_rows' => 0,
    ]);

    $job = new ProcessBulkImportJob($bulkImport);
    $job->handle();

    $bulkImport->refresh();

    // Should complete (not fail) even with some invalid rows
    expect($bulkImport->status)->toBe(BulkImportStatus::Completed);
    expect($bulkImport->success_count)->toBe(3);
    expect($bulkImport->failure_count)->toBe(2);
    expect($bulkImport->processed_rows)->toBe(5);

    // Valid datacenters should be created
    expect(Datacenter::where('name', 'DC One')->exists())->toBeTrue();
    expect(Datacenter::where('name', 'DC Two')->exists())->toBeTrue();
    expect(Datacenter::where('name', 'DC Four')->exists())->toBeTrue();

    // Invalid rows should not create datacenters
    expect(Datacenter::where('name', 'DC Five')->exists())->toBeFalse();
});

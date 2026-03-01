<?php

use App\Enums\BulkImportEntityType;
use App\Enums\BulkImportStatus;
use App\Models\BulkImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bulk import can be created with valid status values', function () {
    $user = User::factory()->create();

    $bulkImport = BulkImport::factory()->create([
        'user_id' => $user->id,
        'entity_type' => BulkImportEntityType::Device,
        'file_name' => 'devices.xlsx',
        'file_path' => 'imports/devices_123.xlsx',
        'status' => BulkImportStatus::Pending,
        'total_rows' => 100,
        'processed_rows' => 0,
        'success_count' => 0,
        'failure_count' => 0,
    ]);

    expect($bulkImport->user_id)->toBe($user->id);
    expect($bulkImport->entity_type)->toBe(BulkImportEntityType::Device);
    expect($bulkImport->file_name)->toBe('devices.xlsx');
    expect($bulkImport->file_path)->toBe('imports/devices_123.xlsx');
    expect($bulkImport->status)->toBe(BulkImportStatus::Pending);
    expect($bulkImport->total_rows)->toBe(100);
    expect($bulkImport->processed_rows)->toBe(0);
    expect($bulkImport->success_count)->toBe(0);
    expect($bulkImport->failure_count)->toBe(0);
});

test('bulk import supports all entity type values', function () {
    $entityTypes = [
        BulkImportEntityType::Datacenter,
        BulkImportEntityType::Room,
        BulkImportEntityType::Row,
        BulkImportEntityType::Rack,
        BulkImportEntityType::Device,
        BulkImportEntityType::Port,
        BulkImportEntityType::Mixed,
    ];

    foreach ($entityTypes as $entityType) {
        $bulkImport = BulkImport::factory()->create([
            'entity_type' => $entityType,
        ]);

        expect($bulkImport->entity_type)->toBe($entityType);
    }
});

test('bulk import status transitions work correctly', function () {
    $bulkImport = BulkImport::factory()->pending()->create();
    expect($bulkImport->status)->toBe(BulkImportStatus::Pending);

    // Transition to processing
    $bulkImport->update([
        'status' => BulkImportStatus::Processing,
        'started_at' => now(),
    ]);
    expect($bulkImport->fresh()->status)->toBe(BulkImportStatus::Processing);
    expect($bulkImport->fresh()->started_at)->not->toBeNull();

    // Transition to completed
    $bulkImport->update([
        'status' => BulkImportStatus::Completed,
        'processed_rows' => 100,
        'success_count' => 95,
        'failure_count' => 5,
        'completed_at' => now(),
    ]);
    expect($bulkImport->fresh()->status)->toBe(BulkImportStatus::Completed);
    expect($bulkImport->fresh()->completed_at)->not->toBeNull();

    // Test failed status
    $failedImport = BulkImport::factory()->pending()->create();
    $failedImport->update([
        'status' => BulkImportStatus::Failed,
        'completed_at' => now(),
    ]);
    expect($failedImport->fresh()->status)->toBe(BulkImportStatus::Failed);
});

test('bulk import belongs to user relationship works correctly', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $bulkImport = BulkImport::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($bulkImport->user)->toBeInstanceOf(User::class);
    expect($bulkImport->user->id)->toBe($user->id);
    expect($bulkImport->user->name)->toBe('John Doe');
    expect($bulkImport->user->email)->toBe('john@example.com');
});

test('bulk import error report path storage and retrieval works', function () {
    // Create import without error report
    $bulkImport = BulkImport::factory()->pending()->create([
        'error_report_path' => null,
    ]);
    expect($bulkImport->error_report_path)->toBeNull();

    // Update with error report path
    $errorPath = 'import-errors/bulk_import_1_errors.csv';
    $bulkImport->update([
        'error_report_path' => $errorPath,
    ]);

    expect($bulkImport->fresh()->error_report_path)->toBe($errorPath);

    // Create import with error report using factory state
    $failedImport = BulkImport::factory()->failed()->create();
    expect($failedImport->error_report_path)->not->toBeNull();
    expect($failedImport->error_report_path)->toContain('import-errors/');
});

test('bulk import progress calculation works correctly', function () {
    // Test 0% progress
    $bulkImport = BulkImport::factory()->pending()->create([
        'total_rows' => 100,
        'processed_rows' => 0,
    ]);
    expect($bulkImport->progressPercentage)->toBe(0.0);

    // Test 50% progress
    $bulkImport->update(['processed_rows' => 50]);
    expect($bulkImport->fresh()->progressPercentage)->toBe(50.0);

    // Test 100% progress
    $bulkImport->update(['processed_rows' => 100]);
    expect($bulkImport->fresh()->progressPercentage)->toBe(100.0);

    // Test with zero total rows (edge case)
    $emptyImport = BulkImport::factory()->create([
        'total_rows' => 0,
        'processed_rows' => 0,
    ]);
    expect($emptyImport->progressPercentage)->toBe(0.0);
});

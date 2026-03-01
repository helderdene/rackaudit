<?php

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Models\BulkExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bulk export can be created with required fields', function () {
    $user = User::factory()->create();

    $bulkExport = BulkExport::factory()->create([
        'user_id' => $user->id,
        'entity_type' => BulkImportEntityType::Device,
        'format' => 'xlsx',
        'file_name' => 'devices_export.xlsx',
        'file_path' => 'exports/devices_123.xlsx',
        'status' => BulkExportStatus::Pending,
        'total_rows' => 100,
        'processed_rows' => 0,
        'filters' => ['datacenter_id' => 1],
    ]);

    expect($bulkExport->user_id)->toBe($user->id);
    expect($bulkExport->entity_type)->toBe(BulkImportEntityType::Device);
    expect($bulkExport->format)->toBe('xlsx');
    expect($bulkExport->file_name)->toBe('devices_export.xlsx');
    expect($bulkExport->file_path)->toBe('exports/devices_123.xlsx');
    expect($bulkExport->status)->toBe(BulkExportStatus::Pending);
    expect($bulkExport->total_rows)->toBe(100);
    expect($bulkExport->processed_rows)->toBe(0);
    expect($bulkExport->filters)->toBe(['datacenter_id' => 1]);
});

test('bulk export belongs to user relationship works correctly', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $bulkExport = BulkExport::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($bulkExport->user)->toBeInstanceOf(User::class);
    expect($bulkExport->user->id)->toBe($user->id);
    expect($bulkExport->user->name)->toBe('Jane Doe');
    expect($bulkExport->user->email)->toBe('jane@example.com');
});

test('bulk export progress percentage calculation works correctly', function () {
    // Test 0% progress
    $bulkExport = BulkExport::factory()->pending()->create([
        'total_rows' => 100,
        'processed_rows' => 0,
    ]);
    expect($bulkExport->progressPercentage)->toBe(0.0);

    // Test 50% progress
    $bulkExport->update(['processed_rows' => 50]);
    expect($bulkExport->fresh()->progressPercentage)->toBe(50.0);

    // Test 100% progress
    $bulkExport->update(['processed_rows' => 100]);
    expect($bulkExport->fresh()->progressPercentage)->toBe(100.0);

    // Test with zero total rows (edge case - avoid division by zero)
    $emptyExport = BulkExport::factory()->create([
        'total_rows' => 0,
        'processed_rows' => 0,
    ]);
    expect($emptyExport->progressPercentage)->toBe(0.0);
});

test('bulk export status enum is cast correctly', function () {
    $bulkExport = BulkExport::factory()->pending()->create();
    expect($bulkExport->status)->toBe(BulkExportStatus::Pending);

    $bulkExport->update(['status' => BulkExportStatus::Processing]);
    expect($bulkExport->fresh()->status)->toBe(BulkExportStatus::Processing);

    $bulkExport->update(['status' => BulkExportStatus::Completed]);
    expect($bulkExport->fresh()->status)->toBe(BulkExportStatus::Completed);

    $bulkExport->update(['status' => BulkExportStatus::Failed]);
    expect($bulkExport->fresh()->status)->toBe(BulkExportStatus::Failed);
});

test('bulk export entity type enum is cast correctly', function () {
    $entityTypes = [
        BulkImportEntityType::Datacenter,
        BulkImportEntityType::Room,
        BulkImportEntityType::Row,
        BulkImportEntityType::Rack,
        BulkImportEntityType::Device,
        BulkImportEntityType::Port,
    ];

    foreach ($entityTypes as $entityType) {
        $bulkExport = BulkExport::factory()->create([
            'entity_type' => $entityType,
        ]);

        expect($bulkExport->entity_type)->toBe($entityType);
    }
});

test('bulk export filters json column is cast to array', function () {
    $filters = [
        'datacenter_id' => 1,
        'room_id' => 2,
        'row_id' => null,
        'rack_id' => null,
    ];

    $bulkExport = BulkExport::factory()->create([
        'filters' => $filters,
    ]);

    expect($bulkExport->filters)->toBe($filters);
    expect($bulkExport->filters)->toBeArray();
    expect($bulkExport->filters['datacenter_id'])->toBe(1);
    expect($bulkExport->filters['room_id'])->toBe(2);

    // Test empty filters
    $noFiltersExport = BulkExport::factory()->create([
        'filters' => [],
    ]);
    expect($noFiltersExport->filters)->toBe([]);
});

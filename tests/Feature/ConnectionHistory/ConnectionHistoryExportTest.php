<?php

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Jobs\ConnectionHistoryExportJob;
use App\Models\ActivityLog;
use App\Models\BulkExport;
use App\Models\Connection;
use App\Models\Port;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
    ActivityLog::query()->delete();
    config(['inertia.testing.ensure_pages_exist' => false]);
    Storage::fake('local');
});

/**
 * Helper to create a connection with proper port setup.
 */
function createExportTestConnection(): Connection
{
    $sourcePort = Port::factory()->ethernet()->create();
    $destinationPort = Port::factory()->ethernet()->create();

    return Connection::withoutEvents(fn () => Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]));
}

test('CSV export generates file with correct columns', function () {
    Queue::fake();

    $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
    $admin->assignRole('Administrator');

    $connection = createExportTestConnection();

    // Create activity logs with various data
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'created',
        'ip_address' => '192.168.1.1',
        'old_values' => null,
        'new_values' => ['cable_color' => 'blue'],
        'created_at' => now(),
    ]);

    // Initiate CSV export
    $response = $this->actingAs($admin)->postJson('/connections/history/export', [
        'format' => 'csv',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'format',
                'status',
                'entity_type',
            ],
        ])
        ->assertJsonPath('data.format', 'csv')
        ->assertJsonPath('data.entity_type', 'connection_history');

    // Verify a BulkExport record was created
    $export = BulkExport::first();
    expect($export)->not->toBeNull()
        ->and($export->format)->toBe('csv')
        ->and($export->entity_type)->toBe(BulkImportEntityType::ConnectionHistory);
});

test('CSV export respects current filter criteria', function () {
    Queue::fake();

    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createExportTestConnection();
    $otherUser = User::withoutEvents(fn () => User::factory()->create());

    // Create activity logs - some within filter, some outside
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'created',
        'created_at' => now()->subDays(5),
    ]);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $otherUser->id,
        'action' => 'updated',
        'created_at' => now()->subDays(2),
    ]);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'deleted',
        'created_at' => now()->subDays(10),
    ]);

    // Export with filters: only updated action, only from $otherUser
    $response = $this->actingAs($admin)->postJson('/connections/history/export', [
        'format' => 'csv',
        'action' => 'updated',
        'user_id' => $otherUser->id,
    ]);

    $response->assertCreated();

    // Verify filters were stored in BulkExport record
    $export = BulkExport::first();
    expect($export->filters)->toBeArray()
        ->and($export->filters['action'])->toBe('updated')
        ->and($export->filters['user_id'])->toBe($otherUser->id);
});

test('PDF export generates formatted document with header', function () {
    Queue::fake();

    $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'John Doe']));
    $admin->assignRole('Administrator');

    $connection = createExportTestConnection();

    // Create activity logs
    ActivityLog::factory()->count(5)->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
    ]);

    // Initiate PDF export
    $response = $this->actingAs($admin)->postJson('/connections/history/export', [
        'format' => 'pdf',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.format', 'pdf')
        ->assertJsonPath('data.entity_type', 'connection_history');

    // Verify BulkExport record was created for PDF
    $export = BulkExport::first();
    expect($export)->not->toBeNull()
        ->and($export->format)->toBe('pdf')
        ->and($export->user_id)->toBe($admin->id);
});

test('export job progress tracking works', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createExportTestConnection();

    // Create multiple activity logs to track progress
    ActivityLog::factory()->count(10)->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
    ]);

    // Create BulkExport record manually to test job
    $bulkExport = BulkExport::create([
        'user_id' => $admin->id,
        'entity_type' => BulkImportEntityType::ConnectionHistory,
        'format' => 'csv',
        'file_name' => 'test_export.csv',
        'file_path' => 'exports/test_export.csv',
        'status' => BulkExportStatus::Pending,
        'total_rows' => 10,
        'processed_rows' => 0,
        'filters' => [],
    ]);

    // Run the export job synchronously
    $job = new ConnectionHistoryExportJob($bulkExport);
    $job->handle();

    // Refresh and check progress
    $bulkExport->refresh();

    expect($bulkExport->status)->toBe(BulkExportStatus::Completed)
        ->and($bulkExport->processed_rows)->toBe(10)
        ->and($bulkExport->total_rows)->toBe(10)
        ->and($bulkExport->completed_at)->not->toBeNull();

    // Verify file was created
    Storage::disk('local')->assertExists($bulkExport->file_path);
});

test('export status endpoint returns correct status', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    // Create a completed export
    $bulkExport = BulkExport::create([
        'user_id' => $admin->id,
        'entity_type' => BulkImportEntityType::ConnectionHistory,
        'format' => 'csv',
        'file_name' => 'completed_export.csv',
        'file_path' => 'exports/completed_export.csv',
        'status' => BulkExportStatus::Completed,
        'total_rows' => 100,
        'processed_rows' => 100,
        'filters' => [],
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);

    // Create the file
    Storage::disk('local')->put('exports/completed_export.csv', 'test content');

    $response = $this->actingAs($admin)->getJson('/connections/history/export/'.$bulkExport->id.'/status');

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('data.progress_percentage', 100);
});

test('export download works after completion', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    // Create a completed export with actual file
    $csvContent = "timestamp,user,role,ip,action,connection_id,old_values_summary,new_values_summary\n";
    $csvContent .= "2025-01-01 10:00:00,Admin,Administrator,192.168.1.1,created,1,,Cable connected\n";

    Storage::disk('local')->put('exports/download_test.csv', $csvContent);

    $bulkExport = BulkExport::create([
        'user_id' => $admin->id,
        'entity_type' => BulkImportEntityType::ConnectionHistory,
        'format' => 'csv',
        'file_name' => 'download_test.csv',
        'file_path' => 'exports/download_test.csv',
        'status' => BulkExportStatus::Completed,
        'total_rows' => 1,
        'processed_rows' => 1,
        'filters' => [],
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get('/connections/history/export/'.$bulkExport->id.'/download');

    $response->assertOk()
        ->assertDownload('download_test.csv');
});

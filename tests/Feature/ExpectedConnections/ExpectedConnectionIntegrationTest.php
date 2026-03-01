<?php

/**
 * Task Group 5: Integration tests for Expected Connection Parsing feature.
 *
 * These tests fill critical gaps in test coverage focusing on:
 * - End-to-end workflows (upload -> parse -> review -> confirm)
 * - Version replacement logic
 * - Mixed match types in parsing
 * - Confirmed connections availability for comparison view
 */

use App\Actions\ExpectedConnections\ParseConnectionsAction;
use App\Enums\CableType;
use App\Enums\ExpectedConnectionStatus;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

/**
 * Helper function to create expected connections without triggering factory device creation.
 *
 * @return \Illuminate\Support\Collection<int, ExpectedConnection>
 */
function createExpectedConnections(
    ImplementationFile $implementationFile,
    Device $sourceDevice,
    Port $sourcePort,
    Device $destDevice,
    Port $destPort,
    ExpectedConnectionStatus $status,
    int $count = 1
): \Illuminate\Support\Collection {
    $connections = collect();
    for ($i = 0; $i < $count; $i++) {
        $connections->push(ExpectedConnection::create([
            'implementation_file_id' => $implementationFile->id,
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
            'cable_type' => CableType::Cat6,
            'cable_length' => 3.5,
            'row_number' => $i + 1,
            'status' => $status,
        ]));
    }

    return $connections;
}

/**
 * Test 1: End-to-end workflow - parse file, then bulk confirm all via API
 */
test('complete workflow: parse file and confirm all connections via API', function () {
    // Setup test devices and ports
    $sourceDevice = Device::factory()->create(['name' => 'Server-Alpha']);
    $destDevice = Device::factory()->create(['name' => 'Switch-Alpha']);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => 'eth0']);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id, 'label' => 'port1']);

    // Create implementation file and store a test file
    $implementationFile = ImplementationFile::factory()->csv()->approved()->create([
        'file_size' => 1024, // 1 KB
    ]);

    // Create CSV content with matching data
    $csvContent = "Source Device,Source Port,Dest Device,Dest Port,Cable Type,Cable Length\n";
    $csvContent .= "Server-Alpha,eth0,Switch-Alpha,port1,Cat6,3.5\n";

    // Store the file
    $tempPath = sys_get_temp_dir().'/test_parse_'.uniqid().'.csv';
    file_put_contents($tempPath, $csvContent);
    Storage::disk('local')->put($implementationFile->file_path, file_get_contents($tempPath));
    @unlink($tempPath);

    // Step 1: Parse the file via API
    $parseResponse = $this->actingAs($this->admin)
        ->postJson(route('implementation-files.parse-connections', $implementationFile));

    $parseResponse->assertSuccessful();
    $parseResponse->assertJsonPath('success', true);

    // Verify expected connections were created in pending_review status
    $connections = ExpectedConnection::where('implementation_file_id', $implementationFile->id)->get();
    expect($connections)->toHaveCount(1);
    expect($connections->first()->status)->toBe(ExpectedConnectionStatus::PendingReview);

    // Step 2: Bulk confirm all connections via API
    $connectionIds = $connections->pluck('id')->toArray();
    $confirmResponse = $this->actingAs($this->admin)
        ->postJson(route('expected-connections.bulk-confirm'), [
            'connection_ids' => $connectionIds,
        ]);

    $confirmResponse->assertSuccessful();
    $confirmResponse->assertJsonPath('confirmed_count', 1);

    // Verify connections are now confirmed
    $connections->each(function ($connection) {
        $connection->refresh();
        expect($connection->status)->toBe(ExpectedConnectionStatus::Confirmed);
    });

    // Cleanup
    Storage::disk('local')->delete($implementationFile->file_path);
});

/**
 * Test 2: Version replacement - new version archives old expected connections
 *
 * Tests that when parsing a new version of an implementation file,
 * previous version's expected connections are soft deleted (archived).
 */
test('uploading new version archives previous version expected connections', function () {
    // Create devices and ports for the CSV data
    $sourceDevice = Device::factory()->create(['name' => 'Server-V2']);
    $destDevice = Device::factory()->create(['name' => 'Switch-V2']);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => 'eth0']);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id, 'label' => 'port1']);

    // Create first implementation file (version 1)
    $file1 = ImplementationFile::factory()->csv()->approved()->create([
        'version_number' => 1,
        'file_size' => 1024,
    ]);
    $file1->update(['version_group_id' => $file1->id]);

    // Create expected connections for version 1 using direct creation
    createExpectedConnections($file1, $sourceDevice, $sourcePort, $destDevice, $destPort, ExpectedConnectionStatus::Confirmed, 3);

    // Verify version 1 connections exist
    expect(ExpectedConnection::where('implementation_file_id', $file1->id)->count())->toBe(3);

    // Create second implementation file (version 2) in the same version group
    $file2 = ImplementationFile::factory()->csv()->approved()->create([
        'version_group_id' => $file1->id,
        'version_number' => 2,
        'datacenter_id' => $file1->datacenter_id,
        'file_size' => 1024,
    ]);

    // Create a valid test file for parsing (with actual connection data)
    $csvContent = "Source Device,Source Port,Dest Device,Dest Port,Cable Type,Cable Length\n";
    $csvContent .= "Server-V2,eth0,Switch-V2,port1,Cat6,3.5\n";

    $tempPath = sys_get_temp_dir().'/test_v2_'.uniqid().'.csv';
    file_put_contents($tempPath, $csvContent);
    Storage::disk('local')->put($file2->file_path, file_get_contents($tempPath));
    @unlink($tempPath);

    // Execute parse action on version 2 (this should archive version 1 connections)
    $action = new ParseConnectionsAction;
    $result = $action->execute($file2);

    // Verify parsing was successful
    expect($result['success'])->toBeTrue();

    // Verify previous version connections are soft deleted
    expect(ExpectedConnection::where('implementation_file_id', $file1->id)->count())->toBe(0);
    expect(ExpectedConnection::withTrashed()->where('implementation_file_id', $file1->id)->count())->toBe(3);

    // Verify new version has connections
    expect(ExpectedConnection::where('implementation_file_id', $file2->id)->count())->toBe(1);

    // Cleanup
    Storage::disk('local')->delete($file2->file_path);
});

/**
 * Test 3: Only confirmed connections available via confirmed scope for comparison view
 */
test('only confirmed connections are returned by confirmed scope for comparison view', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    // Create reusable devices and ports
    $sourceDevice = Device::factory()->create();
    $destDevice = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create connections in different statuses using direct creation
    createExpectedConnections($implementationFile, $sourceDevice, $sourcePort, $destDevice, $destPort, ExpectedConnectionStatus::Confirmed, 4);
    createExpectedConnections($implementationFile, $sourceDevice, $sourcePort, $destDevice, $destPort, ExpectedConnectionStatus::PendingReview, 2);
    createExpectedConnections($implementationFile, $sourceDevice, $sourcePort, $destDevice, $destPort, ExpectedConnectionStatus::Skipped, 1);

    // Query using confirmed scope (used for comparison view)
    $confirmedConnections = ExpectedConnection::confirmed()
        ->where('implementation_file_id', $implementationFile->id)
        ->get();

    expect($confirmedConnections)->toHaveCount(4);
    $confirmedConnections->each(function ($connection) {
        expect($connection->status)->toBe(ExpectedConnectionStatus::Confirmed);
    });

    // Verify total count
    expect(ExpectedConnection::where('implementation_file_id', $implementationFile->id)->count())->toBe(7);
});

/**
 * Test 4: Parsing non-approved file returns error
 */
test('parsing non-approved implementation file returns error', function () {
    $implementationFile = ImplementationFile::factory()->csv()->pendingApproval()->create([
        'file_size' => 1024,
    ]);

    $action = new ParseConnectionsAction;
    $result = $action->execute($implementationFile);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('approved');
});

/**
 * Test 5: Parsing unsupported file types (PDF, Word) returns error
 */
test('parsing unsupported file types returns error', function () {
    // Test PDF file
    $pdfFile = ImplementationFile::factory()->pdf()->approved()->create([
        'file_size' => 1024,
    ]);

    $action = new ParseConnectionsAction;
    $result = $action->execute($pdfFile);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('not supported');

    // Test Word file
    $wordFile = ImplementationFile::factory()->docx()->approved()->create([
        'file_size' => 1024,
    ]);

    $result = $action->execute($wordFile);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('not supported');
});

/**
 * Test 6: Create device and port on the fly and update expected connection
 */
test('create device and port on the fly updates expected connection', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    // Create an unmatched expected connection
    $expectedConnection = ExpectedConnection::factory()
        ->forImplementationFile($implementationFile)
        ->unmatched()
        ->create();

    // Verify connection has no source device/port
    expect($expectedConnection->source_device_id)->toBeNull();
    expect($expectedConnection->source_port_id)->toBeNull();

    // Create device/port via API
    $response = $this->actingAs($this->admin)
        ->postJson(route('expected-connections.create-device-port', $expectedConnection), [
            'device_name' => 'NewServer-001',
            'port_label' => 'eth0',
            'target' => 'source',
        ]);

    $response->assertSuccessful();

    // Verify device was created
    $this->assertDatabaseHas('devices', ['name' => 'NewServer-001']);

    // Verify port was created
    $this->assertDatabaseHas('ports', ['label' => 'eth0']);

    // Verify expected connection was updated with new IDs
    $expectedConnection->refresh();
    expect($expectedConnection->source_device_id)->not->toBeNull();
    expect($expectedConnection->source_port_id)->not->toBeNull();
    expect($expectedConnection->sourceDevice->name)->toBe('NewServer-001');
    expect($expectedConnection->sourcePort->label)->toBe('eth0');
});

/**
 * Test 7: Finalization workflow - all pending connections can be reviewed and finalized
 */
test('finalization workflow - all connections can be confirmed or skipped', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    // Create reusable devices and ports
    $sourceDevice = Device::factory()->create();
    $destDevice = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create pending review connections using direct creation
    $connections = createExpectedConnections(
        $implementationFile,
        $sourceDevice,
        $sourcePort,
        $destDevice,
        $destPort,
        ExpectedConnectionStatus::PendingReview,
        5
    );

    // Confirm 3 connections
    $toConfirm = $connections->take(3)->pluck('id')->toArray();
    $confirmResponse = $this->actingAs($this->admin)
        ->postJson(route('expected-connections.bulk-confirm'), [
            'connection_ids' => $toConfirm,
        ]);

    $confirmResponse->assertSuccessful();
    $confirmResponse->assertJsonPath('confirmed_count', 3);

    // Skip remaining 2 connections
    $toSkip = $connections->skip(3)->pluck('id')->toArray();
    $skipResponse = $this->actingAs($this->admin)
        ->postJson(route('expected-connections.bulk-skip'), [
            'connection_ids' => $toSkip,
        ]);

    $skipResponse->assertSuccessful();
    $skipResponse->assertJsonPath('skipped_count', 2);

    // Verify no more pending connections remain
    $pendingCount = ExpectedConnection::where('implementation_file_id', $implementationFile->id)
        ->where('status', ExpectedConnectionStatus::PendingReview)
        ->count();

    expect($pendingCount)->toBe(0);

    // Verify final counts
    $confirmedCount = ExpectedConnection::where('implementation_file_id', $implementationFile->id)
        ->where('status', ExpectedConnectionStatus::Confirmed)
        ->count();

    $skippedCount = ExpectedConnection::where('implementation_file_id', $implementationFile->id)
        ->where('status', ExpectedConnectionStatus::Skipped)
        ->count();

    expect($confirmedCount)->toBe(3);
    expect($skippedCount)->toBe(2);
});

/**
 * Test 8: Review page returns statistics for all status types
 */
test('review endpoint returns accurate statistics for all connection statuses', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    // Create reusable devices and ports
    $sourceDevice = Device::factory()->create();
    $destDevice = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create connections with different statuses using direct creation
    createExpectedConnections($implementationFile, $sourceDevice, $sourcePort, $destDevice, $destPort, ExpectedConnectionStatus::Confirmed, 5);
    createExpectedConnections($implementationFile, $sourceDevice, $sourcePort, $destDevice, $destPort, ExpectedConnectionStatus::PendingReview, 3);
    createExpectedConnections($implementationFile, $sourceDevice, $sourcePort, $destDevice, $destPort, ExpectedConnectionStatus::Skipped, 2);

    $response = $this->actingAs($this->admin)
        ->getJson(route('expected-connections.index', ['implementation_file' => $implementationFile->id]));

    $response->assertSuccessful();

    // Verify statistics are accurate
    $response->assertJsonPath('statistics.total', 10);
    $response->assertJsonPath('statistics.confirmed', 5);
    $response->assertJsonPath('statistics.pending_review', 3);
    $response->assertJsonPath('statistics.skipped', 2);

    // Verify data count matches total
    $response->assertJsonCount(10, 'data');
});

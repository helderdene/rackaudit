<?php

use App\Enums\ExpectedConnectionStatus;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

test('connection review page returns expected connection data with match status indicators', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    // Create connections with different status types
    $exactMatch = ExpectedConnection::factory()
        ->forImplementationFile($implementationFile)
        ->pendingReview()
        ->create();

    $response = $this->actingAs($this->admin)
        ->getJson(route('expected-connections.index', ['implementation_file' => $implementationFile->id]));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'row_number',
                'source_device',
                'source_port',
                'dest_device',
                'dest_port',
                'status',
                'status_label',
            ],
        ],
        'statistics' => [
            'total',
            'pending_review',
            'confirmed',
            'skipped',
        ],
    ]);

    // Verify the connection data includes all needed fields for visual distinction
    $data = $response->json('data.0');
    expect($data['status'])->toBe('pending_review');
    expect($data['status_label'])->toBe('Pending Review');
});

test('inline editing updates connection mapping via API', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();
    $expectedConnection = ExpectedConnection::factory()
        ->forImplementationFile($implementationFile)
        ->create();

    $newDevice = Device::factory()->create(['name' => 'Updated Server']);
    $newPort = Port::factory()->ethernet()->create([
        'device_id' => $newDevice->id,
        'label' => 'eth-updated',
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson(route('expected-connections.update', $expectedConnection), [
            'source_device_id' => $newDevice->id,
            'source_port_id' => $newPort->id,
        ]);

    $response->assertSuccessful();

    $expectedConnection->refresh();
    expect($expectedConnection->source_device_id)->toBe($newDevice->id);
    expect($expectedConnection->source_port_id)->toBe($newPort->id);
});

test('bulk confirm action confirms all selected connections', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    $connections = ExpectedConnection::factory()
        ->count(4)
        ->forImplementationFile($implementationFile)
        ->pendingReview()
        ->create();

    $connectionIds = $connections->pluck('id')->toArray();

    $response = $this->actingAs($this->admin)
        ->postJson(route('expected-connections.bulk-confirm'), [
            'connection_ids' => $connectionIds,
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('confirmed_count', 4);

    // Verify all connections are confirmed
    foreach ($connections as $connection) {
        $connection->refresh();
        expect($connection->status)->toBe(ExpectedConnectionStatus::Confirmed);
    }
});

test('bulk skip action skips all selected connections', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    $connections = ExpectedConnection::factory()
        ->count(3)
        ->forImplementationFile($implementationFile)
        ->pendingReview()
        ->create();

    $connectionIds = $connections->pluck('id')->toArray();

    $response = $this->actingAs($this->admin)
        ->postJson(route('expected-connections.bulk-skip'), [
            'connection_ids' => $connectionIds,
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('skipped_count', 3);

    // Verify all connections are skipped
    foreach ($connections as $connection) {
        $connection->refresh();
        expect($connection->status)->toBe(ExpectedConnectionStatus::Skipped);
    }
});

test('connection template download endpoints return files', function () {
    // Test Excel template download
    $response = $this->actingAs($this->admin)
        ->get(route('templates.connections.excel'));

    $response->assertSuccessful();
    $response->assertHeader('content-disposition');

    // Test CSV template download
    $csvResponse = $this->actingAs($this->admin)
        ->get(route('templates.connections.csv'));

    $csvResponse->assertSuccessful();
    $csvResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('device port search returns matching devices and ports', function () {
    // Create test devices with ports
    $device1 = Device::factory()->create(['name' => 'Server-001']);
    $device2 = Device::factory()->create(['name' => 'Server-002']);
    Port::factory()->ethernet()->create(['device_id' => $device1->id, 'label' => 'eth0']);
    Port::factory()->ethernet()->create(['device_id' => $device1->id, 'label' => 'eth1']);
    Port::factory()->ethernet()->create(['device_id' => $device2->id, 'label' => 'eth0']);

    // Search devices by name
    $response = $this->actingAs($this->admin)
        ->getJson(route('devices.index', ['search' => 'Server']));

    $response->assertSuccessful();

    // Search ports for a specific device
    $portResponse = $this->actingAs($this->admin)
        ->getJson(route('devices.ports.index', ['device' => $device1->id]));

    $portResponse->assertSuccessful();
});

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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->manager = User::factory()->create();
    $this->manager->assignRole('IT Manager');

    // Viewer has view-only access to resources, should not be able to manage expected connections
    $this->viewerUser = User::factory()->create();
    $this->viewerUser->assignRole('Viewer');
});

test('can list parsed connections for an implementation file', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    $connections = ExpectedConnection::factory()
        ->count(3)
        ->forImplementationFile($implementationFile)
        ->create();

    $response = $this->actingAs($this->admin)
        ->getJson(route('expected-connections.index', ['implementation_file' => $implementationFile->id]));

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'data');
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'row_number',
                'source_device',
                'source_port',
                'dest_device',
                'dest_port',
                'cable_type',
                'cable_length',
                'status',
            ],
        ],
        'statistics' => [
            'total',
            'pending_review',
            'confirmed',
            'skipped',
        ],
    ]);
});

test('can update individual expected connection device/port mapping', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();
    $expectedConnection = ExpectedConnection::factory()
        ->forImplementationFile($implementationFile)
        ->create();

    $newDevice = Device::factory()->create(['name' => 'New Server']);
    $newPort = Port::factory()->ethernet()->create(['device_id' => $newDevice->id, 'label' => 'eth1']);

    $response = $this->actingAs($this->admin)
        ->putJson(route('expected-connections.update', $expectedConnection), [
            'source_device_id' => $newDevice->id,
            'source_port_id' => $newPort->id,
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('data.source_device.id', $newDevice->id);
    $response->assertJsonPath('data.source_port.id', $newPort->id);

    $expectedConnection->refresh();
    expect($expectedConnection->source_device_id)->toBe($newDevice->id);
    expect($expectedConnection->source_port_id)->toBe($newPort->id);
});

test('can bulk confirm matched connections', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    $connections = ExpectedConnection::factory()
        ->count(5)
        ->forImplementationFile($implementationFile)
        ->pendingReview()
        ->create();

    $connectionIds = $connections->pluck('id')->toArray();

    $response = $this->actingAs($this->admin)
        ->postJson(route('expected-connections.bulk-confirm'), [
            'connection_ids' => $connectionIds,
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('confirmed_count', 5);

    foreach ($connections as $connection) {
        $connection->refresh();
        expect($connection->status)->toBe(ExpectedConnectionStatus::Confirmed);
    }
});

test('can bulk skip unrecognized connections', function () {
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

    foreach ($connections as $connection) {
        $connection->refresh();
        expect($connection->status)->toBe(ExpectedConnectionStatus::Skipped);
    }
});

test('can create device and port on the fly for unrecognized entries', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    // Create an unmatched expected connection
    $expectedConnection = ExpectedConnection::factory()
        ->forImplementationFile($implementationFile)
        ->unmatched()
        ->create();

    $response = $this->actingAs($this->admin)
        ->postJson(route('expected-connections.create-device-port', $expectedConnection), [
            'device_name' => 'New Server Alpha',
            'port_label' => 'eth0',
            'target' => 'source',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'id',
            'source_device',
            'source_port',
        ],
        'created_device' => [
            'id',
            'name',
        ],
        'created_port' => [
            'id',
            'label',
        ],
    ]);

    // Verify database has the new device and port
    $this->assertDatabaseHas('devices', ['name' => 'New Server Alpha']);
    $this->assertDatabaseHas('ports', ['label' => 'eth0']);

    // Verify expected connection was updated
    $expectedConnection->refresh();
    expect($expectedConnection->source_device_id)->not->toBeNull();
    expect($expectedConnection->source_port_id)->not->toBeNull();
});

test('only authorized users can review expected connections', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();
    ExpectedConnection::factory()
        ->count(2)
        ->forImplementationFile($implementationFile)
        ->create();

    // Viewer should not have access to manage expected connections
    $response = $this->actingAs($this->viewerUser)
        ->getJson(route('expected-connections.index', ['implementation_file' => $implementationFile->id]));

    $response->assertForbidden();

    // Admin should have access
    $response = $this->actingAs($this->admin)
        ->getJson(route('expected-connections.index', ['implementation_file' => $implementationFile->id]));

    $response->assertSuccessful();

    // IT Manager should have access
    $response = $this->actingAs($this->manager)
        ->getJson(route('expected-connections.index', ['implementation_file' => $implementationFile->id]));

    $response->assertSuccessful();
});

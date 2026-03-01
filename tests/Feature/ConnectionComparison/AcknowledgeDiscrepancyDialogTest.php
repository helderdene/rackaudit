<?php

/**
 * Tests for Acknowledge Discrepancy Dialog Component.
 *
 * These tests verify:
 * - Dialog receives correct discrepancy information from API
 * - Form submission creates an acknowledgment successfully
 * - Dialog triggers refresh on successful acknowledgment
 */

use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('IT Manager');
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create(['row_id' => $this->row->id]);
    $this->file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
});

it('receives correct discrepancy information for missing connection', function () {
    // Create a missing expected connection (no actual connection exists)
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Source Server']);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Dest Switch']);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => 'eth0']);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id, 'label' => 'port1']);

    $expectedConnection = ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Get comparison data from API (this is what the dialog would receive)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');

    // Find the missing result
    $missingResult = collect($data)->firstWhere('discrepancy_type', 'missing');
    expect($missingResult)->not->toBeNull();

    // Verify the dialog would receive the correct discrepancy info
    expect($missingResult['discrepancy_type'])->toBe('missing');
    expect($missingResult['discrepancy_type_label'])->toBe('Missing');
    expect($missingResult['expected_connection'])->not->toBeNull();
    expect($missingResult['expected_connection']['id'])->toBe($expectedConnection->id);
    expect($missingResult['source_device']['name'])->toBe('Source Server');
    expect($missingResult['source_port']['label'])->toBe('eth0');
    expect($missingResult['dest_device']['name'])->toBe('Dest Switch');
    expect($missingResult['dest_port']['label'])->toBe('port1');
});

it('creates acknowledgment successfully with notes', function () {
    // Create a missing expected connection
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    $expectedConnection = ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Submit acknowledgment (like the dialog form would do)
    $response = $this->actingAs($this->user)
        ->postJson('/api/discrepancy-acknowledgments', [
            'expected_connection_id' => $expectedConnection->id,
            'discrepancy_type' => 'missing',
            'notes' => 'Cable is on order, expected delivery next week.',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'expected_connection_id',
                'connection_id',
                'discrepancy_type',
                'discrepancy_type_label',
                'acknowledged_by',
                'acknowledged_by_name',
                'acknowledged_at',
                'notes',
            ],
            'message',
        ]);

    // Verify the acknowledgment was created in database
    $this->assertDatabaseHas('discrepancy_acknowledgments', [
        'expected_connection_id' => $expectedConnection->id,
        'discrepancy_type' => 'missing',
        'acknowledged_by' => $this->user->id,
        'notes' => 'Cable is on order, expected delivery next week.',
    ]);

    // Verify response data
    $responseData = $response->json('data');
    expect($responseData['expected_connection_id'])->toBe($expectedConnection->id);
    expect($responseData['discrepancy_type'])->toBe('missing');
    expect($responseData['notes'])->toBe('Cable is on order, expected delivery next week.');
    expect($responseData['acknowledged_by_name'])->toBe($this->user->name);
});

it('shows acknowledged status in comparison after successful acknowledgment', function () {
    // Create a missing expected connection
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    $expectedConnection = ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // First verify the connection is not acknowledged
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison");

    $response->assertOk();
    $initialData = $response->json('data');
    $initialMissing = collect($initialData)->firstWhere('discrepancy_type', 'missing');
    expect($initialMissing['is_acknowledged'])->toBeFalse();
    expect($initialMissing['acknowledgment'])->toBeNull();

    // Create acknowledgment (simulating dialog submission)
    $this->actingAs($this->user)
        ->postJson('/api/discrepancy-acknowledgments', [
            'expected_connection_id' => $expectedConnection->id,
            'discrepancy_type' => 'missing',
            'notes' => 'Waiting for hardware.',
        ])
        ->assertCreated();

    // Refresh comparison data (simulating dialog close and table refresh)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison");

    $response->assertOk();
    $refreshedData = $response->json('data');

    // Find the same missing result - it should now be acknowledged
    $acknowledgedResult = collect($refreshedData)->firstWhere(function ($item) use ($expectedConnection) {
        return $item['expected_connection'] !== null
            && $item['expected_connection']['id'] === $expectedConnection->id;
    });

    expect($acknowledgedResult)->not->toBeNull();
    expect($acknowledgedResult['is_acknowledged'])->toBeTrue();
    expect($acknowledgedResult['acknowledgment'])->not->toBeNull();
    expect($acknowledgedResult['acknowledgment']['notes'])->toBe('Waiting for hardware.');
    // The API returns acknowledged_by as the user ID and acknowledged_by_name as the user name
    expect($acknowledgedResult['acknowledgment']['acknowledged_by'])->toBe($this->user->id);
    expect($acknowledgedResult['acknowledgment']['acknowledged_by_name'])->toBe($this->user->name);
});

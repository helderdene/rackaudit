<?php

use App\Enums\DiscrepancyType;
use App\Models\Connection;
use App\Models\DiscrepancyAcknowledgment;
use App\Models\ExpectedConnection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create an acknowledgment with expected_connection_id', function () {
    $expectedConnection = ExpectedConnection::factory()->confirmed()->create();
    $user = User::factory()->create();

    $acknowledgment = DiscrepancyAcknowledgment::create([
        'expected_connection_id' => $expectedConnection->id,
        'connection_id' => null,
        'discrepancy_type' => DiscrepancyType::Missing,
        'acknowledged_by' => $user->id,
        'acknowledged_at' => now(),
        'notes' => 'This connection is pending installation.',
    ]);

    expect($acknowledgment)->toBeInstanceOf(DiscrepancyAcknowledgment::class)
        ->and($acknowledgment->expected_connection_id)->toBe($expectedConnection->id)
        ->and($acknowledgment->connection_id)->toBeNull()
        ->and($acknowledgment->expectedConnection->id)->toBe($expectedConnection->id);
});

it('can create an acknowledgment with connection_id', function () {
    $connection = Connection::factory()->create();
    $user = User::factory()->create();

    $acknowledgment = DiscrepancyAcknowledgment::create([
        'expected_connection_id' => null,
        'connection_id' => $connection->id,
        'discrepancy_type' => DiscrepancyType::Unexpected,
        'acknowledged_by' => $user->id,
        'acknowledged_at' => now(),
        'notes' => 'Temporary connection for testing.',
    ]);

    expect($acknowledgment)->toBeInstanceOf(DiscrepancyAcknowledgment::class)
        ->and($acknowledgment->connection_id)->toBe($connection->id)
        ->and($acknowledgment->expected_connection_id)->toBeNull()
        ->and($acknowledgment->connection->id)->toBe($connection->id);
});

it('has belongs to relationship with User as acknowledged_by', function () {
    $user = User::factory()->create();
    $expectedConnection = ExpectedConnection::factory()->confirmed()->create();

    $acknowledgment = DiscrepancyAcknowledgment::create([
        'expected_connection_id' => $expectedConnection->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'acknowledged_by' => $user->id,
        'acknowledged_at' => now(),
    ]);

    expect($acknowledgment->acknowledgedBy)->toBeInstanceOf(User::class)
        ->and($acknowledgment->acknowledgedBy->id)->toBe($user->id);
});

it('casts discrepancy_type to DiscrepancyType enum', function () {
    $user = User::factory()->create();
    $expectedConnection = ExpectedConnection::factory()->confirmed()->create();

    $acknowledgment = DiscrepancyAcknowledgment::create([
        'expected_connection_id' => $expectedConnection->id,
        'discrepancy_type' => DiscrepancyType::Mismatched,
        'acknowledged_by' => $user->id,
        'acknowledged_at' => now(),
    ]);

    $acknowledgment->refresh();

    expect($acknowledgment->discrepancy_type)->toBeInstanceOf(DiscrepancyType::class)
        ->and($acknowledgment->discrepancy_type)->toBe(DiscrepancyType::Mismatched)
        ->and($acknowledgment->discrepancy_type->label())->toBe('Mismatched');
});

it('allows nullable notes field', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->create();

    $acknowledgment = DiscrepancyAcknowledgment::create([
        'connection_id' => $connection->id,
        'discrepancy_type' => DiscrepancyType::Unexpected,
        'acknowledged_by' => $user->id,
        'acknowledged_at' => now(),
        'notes' => null,
    ]);

    expect($acknowledgment->notes)->toBeNull();

    // Update with notes
    $acknowledgment->update(['notes' => 'Updated notes']);
    expect($acknowledgment->fresh()->notes)->toBe('Updated notes');
});

it('enforces unique constraint on expected_connection_id, connection_id, and discrepancy_type combination', function () {
    $user = User::factory()->create();
    $expectedConnection = ExpectedConnection::factory()->confirmed()->create();
    $connection = Connection::factory()->create();

    // Create first acknowledgment
    DiscrepancyAcknowledgment::create([
        'expected_connection_id' => $expectedConnection->id,
        'connection_id' => $connection->id,
        'discrepancy_type' => DiscrepancyType::Mismatched,
        'acknowledged_by' => $user->id,
        'acknowledged_at' => now(),
    ]);

    // Attempt to create duplicate
    expect(fn () => DiscrepancyAcknowledgment::create([
        'expected_connection_id' => $expectedConnection->id,
        'connection_id' => $connection->id,
        'discrepancy_type' => DiscrepancyType::Mismatched,
        'acknowledged_by' => $user->id,
        'acknowledged_at' => now(),
    ]))->toThrow(QueryException::class);
});

<?php

namespace Database\Factories;

use App\Enums\DiscrepancyType;
use App\Models\Connection;
use App\Models\DiscrepancyAcknowledgment;
use App\Models\ExpectedConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating DiscrepancyAcknowledgment test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscrepancyAcknowledgment>
 */
class DiscrepancyAcknowledgmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = DiscrepancyAcknowledgment::class;

    /**
     * Define the model's default state.
     *
     * Default generates an acknowledgment for a missing expected connection.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expected_connection_id' => ExpectedConnection::factory()->confirmed(),
            'connection_id' => null,
            'discrepancy_type' => DiscrepancyType::Missing,
            'acknowledged_by' => User::factory(),
            'acknowledged_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }

    /**
     * Create an acknowledgment for a matched discrepancy.
     */
    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => ExpectedConnection::factory()->confirmed(),
            'connection_id' => Connection::factory(),
            'discrepancy_type' => DiscrepancyType::Matched,
        ]);
    }

    /**
     * Create an acknowledgment for a missing expected connection.
     */
    public function missing(): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => ExpectedConnection::factory()->confirmed(),
            'connection_id' => null,
            'discrepancy_type' => DiscrepancyType::Missing,
        ]);
    }

    /**
     * Create an acknowledgment for an unexpected actual connection.
     */
    public function unexpected(): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => null,
            'connection_id' => Connection::factory(),
            'discrepancy_type' => DiscrepancyType::Unexpected,
        ]);
    }

    /**
     * Create an acknowledgment for a mismatched connection (partial match).
     */
    public function mismatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => ExpectedConnection::factory()->confirmed(),
            'connection_id' => Connection::factory(),
            'discrepancy_type' => DiscrepancyType::Mismatched,
        ]);
    }

    /**
     * Create an acknowledgment for a conflicting expectation.
     */
    public function conflicting(): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => ExpectedConnection::factory()->confirmed(),
            'connection_id' => null,
            'discrepancy_type' => DiscrepancyType::Conflicting,
        ]);
    }

    /**
     * Create an acknowledgment for an expected connection vs actual connection comparison.
     */
    public function forExpectedAndActual(ExpectedConnection $expectedConnection, Connection $connection): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => $expectedConnection->id,
            'connection_id' => $connection->id,
        ]);
    }

    /**
     * Create an acknowledgment for a specific expected connection.
     */
    public function forExpectedConnection(ExpectedConnection $expectedConnection): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => $expectedConnection->id,
        ]);
    }

    /**
     * Create an acknowledgment for a specific actual connection.
     */
    public function forConnection(Connection $connection): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_id' => $connection->id,
        ]);
    }

    /**
     * Create an acknowledgment by a specific user.
     */
    public function acknowledgedByUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged_by' => $user->id,
        ]);
    }

    /**
     * Create an acknowledgment with specific notes.
     */
    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }

    /**
     * Create an acknowledgment without notes.
     */
    public function withoutNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => null,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Models\Audit;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Discrepancy;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Discrepancy test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Discrepancy>
 */
class DiscrepancyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Discrepancy::class;

    /**
     * Define the model's default state.
     *
     * Default generates an open discrepancy with a random type.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'datacenter_id' => Datacenter::factory(),
            'room_id' => null,
            'implementation_file_id' => null,
            'discrepancy_type' => fake()->randomElement([
                DiscrepancyType::Missing,
                DiscrepancyType::Unexpected,
                DiscrepancyType::Mismatched,
                DiscrepancyType::Conflicting,
            ]),
            'status' => DiscrepancyStatus::Open,
            'source_port_id' => Port::factory(),
            'dest_port_id' => Port::factory(),
            'connection_id' => null,
            'expected_connection_id' => null,
            'expected_config' => null,
            'actual_config' => null,
            'mismatch_details' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.7)->paragraph(),
            'detected_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'resolved_at' => null,
            'resolved_by' => null,
            'audit_id' => null,
            'finding_id' => null,
        ];
    }

    /**
     * Create a discrepancy with open status.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscrepancyStatus::Open,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }

    /**
     * Create a discrepancy with acknowledged status.
     */
    public function acknowledged(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscrepancyStatus::Acknowledged,
            'acknowledged_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'acknowledged_by' => $user?->id ?? User::factory(),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }

    /**
     * Create a discrepancy with resolved status.
     */
    public function resolved(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscrepancyStatus::Resolved,
            'acknowledged_at' => fake()->dateTimeBetween('-2 weeks', '-1 week'),
            'acknowledged_by' => $user?->id ?? User::factory(),
            'resolved_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'resolved_by' => $user?->id ?? User::factory(),
        ]);
    }

    /**
     * Create a discrepancy with in audit status.
     */
    public function inAudit(?Audit $audit = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscrepancyStatus::InAudit,
            'audit_id' => $audit?->id ?? Audit::factory(),
            'acknowledged_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'acknowledged_by' => User::factory(),
        ]);
    }

    /**
     * Create a missing connection discrepancy.
     *
     * Note: Does not automatically create an expected_connection to avoid
     * factory exhaustion. Set expected_connection_id explicitly if needed.
     */
    public function missing(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancy_type' => DiscrepancyType::Missing,
            'title' => 'Missing Connection',
            'connection_id' => null,
        ]);
    }

    /**
     * Create an unexpected connection discrepancy.
     *
     * Note: Does not automatically create a connection to avoid
     * factory exhaustion. Set connection_id explicitly if needed.
     */
    public function unexpected(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancy_type' => DiscrepancyType::Unexpected,
            'title' => 'Unexpected Connection',
            'expected_connection_id' => null,
        ]);
    }

    /**
     * Create a mismatched connection discrepancy.
     *
     * Note: Does not automatically create connection or expected_connection
     * to avoid factory exhaustion. Set IDs explicitly if needed.
     */
    public function mismatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancy_type' => DiscrepancyType::Mismatched,
            'title' => 'Mismatched Connection',
        ]);
    }

    /**
     * Create a conflicting connection discrepancy.
     */
    public function conflicting(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancy_type' => DiscrepancyType::Conflicting,
            'title' => 'Conflicting Connection',
        ]);
    }

    /**
     * Create a configuration mismatch discrepancy.
     */
    public function configurationMismatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancy_type' => DiscrepancyType::ConfigurationMismatch,
            'title' => 'Configuration Mismatch',
            'expected_config' => [
                'cable_type' => 'cat6',
                'cable_length' => 5.0,
            ],
            'actual_config' => [
                'cable_type' => 'cat5e',
                'cable_length' => 3.5,
            ],
            'mismatch_details' => [
                'cable_type' => [
                    'expected' => 'cat6',
                    'actual' => 'cat5e',
                ],
            ],
        ]);
    }

    /**
     * Create a discrepancy for a specific datacenter.
     */
    public function forDatacenter(Datacenter $datacenter): static
    {
        return $this->state(fn (array $attributes) => [
            'datacenter_id' => $datacenter->id,
        ]);
    }

    /**
     * Create a discrepancy for a specific room.
     */
    public function forRoom(Room $room): static
    {
        return $this->state(fn (array $attributes) => [
            'datacenter_id' => $room->datacenter_id,
            'room_id' => $room->id,
        ]);
    }

    /**
     * Create a discrepancy for a specific implementation file.
     */
    public function forImplementationFile(ImplementationFile $file): static
    {
        return $this->state(fn (array $attributes) => [
            'datacenter_id' => $file->datacenter_id,
            'implementation_file_id' => $file->id,
        ]);
    }

    /**
     * Create a discrepancy with a specific connection.
     */
    public function withConnection(Connection $connection): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_id' => $connection->id,
        ]);
    }

    /**
     * Create a discrepancy with a specific expected connection.
     */
    public function withExpectedConnection(ExpectedConnection $expectedConnection): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => $expectedConnection->id,
        ]);
    }

    /**
     * Create a discrepancy with specific title.
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $title,
        ]);
    }

    /**
     * Create a discrepancy with specific description.
     */
    public function withDescription(string $description): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $description,
        ]);
    }
}

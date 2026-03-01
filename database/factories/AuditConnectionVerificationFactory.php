<?php

namespace Database\Factories;

use App\Enums\DiscrepancyType;
use App\Enums\VerificationStatus;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\Connection;
use App\Models\ExpectedConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating AuditConnectionVerification test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditConnectionVerification>
 */
class AuditConnectionVerificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = AuditConnectionVerification::class;

    /**
     * Define the model's default state.
     *
     * Default generates a pending verification for a matched connection.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_id' => Audit::factory(),
            'expected_connection_id' => ExpectedConnection::factory()->confirmed(),
            'connection_id' => Connection::factory(),
            'discrepancy_type' => null,
            'comparison_status' => DiscrepancyType::Matched,
            'verification_status' => VerificationStatus::Pending,
            'notes' => null,
            'verified_by' => null,
            'verified_at' => null,
            'locked_by' => null,
            'locked_at' => null,
        ];
    }

    /**
     * Create a verification with matched comparison status.
     */
    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'comparison_status' => DiscrepancyType::Matched,
        ]);
    }

    /**
     * Create a verification for a missing expected connection.
     */
    public function missing(): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => ExpectedConnection::factory()->confirmed(),
            'connection_id' => null,
            'comparison_status' => DiscrepancyType::Missing,
        ]);
    }

    /**
     * Create a verification for an unexpected actual connection.
     */
    public function unexpected(): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => null,
            'connection_id' => Connection::factory(),
            'comparison_status' => DiscrepancyType::Unexpected,
        ]);
    }

    /**
     * Create a verification for a mismatched connection.
     */
    public function mismatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'comparison_status' => DiscrepancyType::Mismatched,
        ]);
    }

    /**
     * Create a verification for a conflicting connection.
     */
    public function conflicting(): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_connection_id' => ExpectedConnection::factory()->confirmed(),
            'connection_id' => null,
            'comparison_status' => DiscrepancyType::Conflicting,
        ]);
    }

    /**
     * Create a pending verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::Pending,
            'verified_by' => null,
            'verified_at' => null,
        ]);
    }

    /**
     * Create a verified verification.
     */
    public function verified(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::Verified,
            'verified_by' => $user?->id ?? User::factory(),
            'verified_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'notes' => fake()->optional(0.5)->sentence(),
        ]);
    }

    /**
     * Create a discrepant verification.
     */
    public function discrepant(?User $user = null, ?DiscrepancyType $type = null): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::Discrepant,
            'discrepancy_type' => $type ?? fake()->randomElement([
                DiscrepancyType::Missing,
                DiscrepancyType::Unexpected,
                DiscrepancyType::Mismatched,
                DiscrepancyType::Conflicting,
            ]),
            'verified_by' => $user?->id ?? User::factory(),
            'verified_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'notes' => fake()->sentence(),
        ]);
    }

    /**
     * Create a verification that is locked by a user.
     */
    public function locked(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_by' => $user?->id ?? User::factory(),
            'locked_at' => now(),
        ]);
    }

    /**
     * Create a verification with an expired lock.
     */
    public function expiredLock(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_by' => $user?->id ?? User::factory(),
            'locked_at' => now()->subMinutes(AuditConnectionVerification::LOCK_EXPIRATION_MINUTES + 1),
        ]);
    }

    /**
     * Create a verification for a specific audit.
     */
    public function forAudit(Audit $audit): static
    {
        return $this->state(fn (array $attributes) => [
            'audit_id' => $audit->id,
        ]);
    }

    /**
     * Create a verification with specific notes.
     */
    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }
}

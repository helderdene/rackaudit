<?php

namespace Database\Factories;

use App\Models\Audit;
use App\Models\AuditRackVerification;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating AuditRackVerification test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditRackVerification>
 */
class AuditRackVerificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = AuditRackVerification::class;

    /**
     * Define the model's default state.
     *
     * Default generates a pending (unverified) empty rack verification.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_id' => Audit::factory()->inventoryType(),
            'rack_id' => Rack::factory(),
            'verified' => false,
            'notes' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }

    /**
     * Create a pending (unverified) rack verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);
    }

    /**
     * Create a verified rack verification.
     */
    public function verified(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'verified' => true,
            'verified_by' => $user?->id ?? User::factory(),
            'verified_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'notes' => fake()->optional(0.5)->sentence(),
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
     * Create a verification for a specific rack.
     */
    public function forRack(Rack $rack): static
    {
        return $this->state(fn (array $attributes) => [
            'rack_id' => $rack->id,
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

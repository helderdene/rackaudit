<?php

namespace Database\Factories;

use App\Enums\DeviceVerificationStatus;
use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Models\Device;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating AuditDeviceVerification test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditDeviceVerification>
 */
class AuditDeviceVerificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = AuditDeviceVerification::class;

    /**
     * Define the model's default state.
     *
     * Default generates a pending verification for a device.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_id' => Audit::factory()->inventoryType(),
            'device_id' => Device::factory(),
            'rack_id' => Rack::factory(),
            'verification_status' => DeviceVerificationStatus::Pending,
            'notes' => null,
            'verified_by' => null,
            'verified_at' => null,
            'locked_by' => null,
            'locked_at' => null,
        ];
    }

    /**
     * Create a pending verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => DeviceVerificationStatus::Pending,
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
            'verification_status' => DeviceVerificationStatus::Verified,
            'verified_by' => $user?->id ?? User::factory(),
            'verified_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'notes' => fake()->optional(0.5)->sentence(),
        ]);
    }

    /**
     * Create a not found verification.
     */
    public function notFound(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => DeviceVerificationStatus::NotFound,
            'verified_by' => $user?->id ?? User::factory(),
            'verified_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'notes' => fake()->sentence(),
        ]);
    }

    /**
     * Create a discrepant verification.
     */
    public function discrepant(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => DeviceVerificationStatus::Discrepant,
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
            'locked_at' => now()->subMinutes(AuditDeviceVerification::LOCK_EXPIRATION_MINUTES + 1),
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
     * Create a verification for a specific device.
     */
    public function forDevice(Device $device): static
    {
        return $this->state(fn (array $attributes) => [
            'device_id' => $device->id,
            'rack_id' => $device->rack_id,
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

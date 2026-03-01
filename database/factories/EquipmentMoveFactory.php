<?php

namespace Database\Factories;

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating EquipmentMove test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EquipmentMove>
 */
class EquipmentMoveFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = EquipmentMove::class;

    /**
     * Define the model's default state.
     *
     * Default creates a pending_approval move request.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sourceRack = Rack::factory();
        $destinationRack = Rack::factory();

        return [
            'device_id' => Device::factory()->placed(),
            'source_rack_id' => $sourceRack,
            'destination_rack_id' => $destinationRack,
            'source_start_u' => fake()->numberBetween(1, 40),
            'destination_start_u' => fake()->numberBetween(1, 40),
            'source_rack_face' => DeviceRackFace::Front,
            'destination_rack_face' => DeviceRackFace::Front,
            'source_width_type' => DeviceWidthType::Full,
            'destination_width_type' => DeviceWidthType::Full,
            'status' => 'pending_approval',
            'connections_snapshot' => $this->generateSampleConnectionsSnapshot(),
            'requested_by' => User::factory(),
            'approved_by' => null,
            'operator_notes' => fake()->optional(0.6)->sentence(),
            'approval_notes' => null,
            'requested_at' => now(),
            'approved_at' => null,
            'executed_at' => null,
        ];
    }

    /**
     * Generate sample connections snapshot data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function generateSampleConnectionsSnapshot(): array
    {
        $connectionCount = fake()->numberBetween(0, 5);

        if ($connectionCount === 0) {
            return [];
        }

        $connections = [];
        $cableColors = ['blue', 'yellow', 'green', 'red', 'white', 'gray', 'orange'];
        $cableTypes = ['Cat5e', 'Cat6', 'Cat6a', 'Fiber SM', 'Fiber MM'];

        for ($i = 1; $i <= $connectionCount; $i++) {
            $connections[] = [
                'id' => $i,
                'source_port_label' => 'eth'.$i,
                'destination_port_label' => 'sw-port-'.$i,
                'cable_type' => fake()->randomElement($cableTypes),
                'cable_length' => fake()->randomFloat(2, 0.5, 50),
                'cable_color' => fake()->randomElement($cableColors),
                'destination_device_name' => 'Switch '.fake()->numberBetween(1, 10),
            ];
        }

        return $connections;
    }

    /**
     * Indicate that the move has been approved.
     */
    public function approved(?User $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => $approver?->id ?? User::factory(),
            'approved_at' => now(),
            'approval_notes' => fake()->optional(0.5)->sentence(),
        ]);
    }

    /**
     * Indicate that the move has been rejected.
     */
    public function rejected(?User $approver = null, ?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => $approver?->id ?? User::factory(),
            'approved_at' => now(),
            'approval_notes' => $notes ?? fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the move has been executed.
     */
    public function executed(?User $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'executed',
            'approved_by' => $approver?->id ?? User::factory(),
            'approved_at' => now()->subHours(fake()->numberBetween(1, 24)),
            'executed_at' => now(),
            'approval_notes' => fake()->optional(0.3)->sentence(),
        ]);
    }

    /**
     * Indicate that the move has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Set specific source and destination racks.
     */
    public function betweenRacks(Rack $sourceRack, Rack $destinationRack): static
    {
        return $this->state(fn (array $attributes) => [
            'source_rack_id' => $sourceRack->id,
            'destination_rack_id' => $destinationRack->id,
        ]);
    }

    /**
     * Set for an intra-rack move (same rack, different U position).
     */
    public function intraRack(Rack $rack): static
    {
        return $this->state(fn (array $attributes) => [
            'source_rack_id' => $rack->id,
            'destination_rack_id' => $rack->id,
        ]);
    }

    /**
     * Set the device being moved.
     */
    public function forDevice(Device $device): static
    {
        return $this->state(fn (array $attributes) => [
            'device_id' => $device->id,
            'source_rack_id' => $device->rack_id,
            'source_start_u' => $device->start_u,
            'source_rack_face' => $device->rack_face,
            'source_width_type' => $device->width_type,
        ]);
    }

    /**
     * Set the requester user.
     */
    public function requestedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'requested_by' => $user->id,
        ]);
    }

    /**
     * Set with no connections (empty snapshot).
     */
    public function withoutConnections(): static
    {
        return $this->state(fn (array $attributes) => [
            'connections_snapshot' => [],
        ]);
    }

    /**
     * Set with specific connections snapshot data.
     *
     * @param  array<int, array<string, mixed>>  $connections
     */
    public function withConnectionsSnapshot(array $connections): static
    {
        return $this->state(fn (array $attributes) => [
            'connections_snapshot' => $connections,
        ]);
    }

    /**
     * Set destination to rear rack face.
     */
    public function destinationRear(): static
    {
        return $this->state(fn (array $attributes) => [
            'destination_rack_face' => DeviceRackFace::Rear,
        ]);
    }

    /**
     * Set destination to half-left width.
     */
    public function destinationHalfLeft(): static
    {
        return $this->state(fn (array $attributes) => [
            'destination_width_type' => DeviceWidthType::HalfLeft,
        ]);
    }

    /**
     * Set destination to half-right width.
     */
    public function destinationHalfRight(): static
    {
        return $this->state(fn (array $attributes) => [
            'destination_width_type' => DeviceWidthType::HalfRight,
        ]);
    }
}

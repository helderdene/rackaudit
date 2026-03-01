<?php

namespace Database\Factories;

use App\Models\DeviceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating DeviceType test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceType>
 */
class DeviceTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = DeviceType::class;

    /**
     * Realistic device type names for datacenter equipment.
     *
     * @var array<string>
     */
    private array $deviceTypeNames = [
        'Server',
        'Switch',
        'Router',
        'Storage',
        'PDU',
        'UPS',
        'Patch Panel',
        'KVM',
        'Console Server',
        'Blade Chassis',
        'Firewall',
        'Load Balancer',
        'SAN Controller',
        'Tape Library',
        'Cable Management',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Use a combination of device type name and unique suffix to avoid collisions
        $baseName = fake()->randomElement($this->deviceTypeNames);
        $uniqueSuffix = fake()->unique()->randomNumber(6);

        return [
            'name' => "{$baseName} {$uniqueSuffix}",
            'description' => fake()->optional(0.7)->sentence(),
            'default_u_size' => fake()->numberBetween(1, 4),
        ];
    }

    /**
     * Indicate a server device type.
     */
    public function server(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Server',
            'description' => 'Physical or virtual server for compute workloads',
            'default_u_size' => 2,
        ]);
    }

    /**
     * Indicate a network switch device type.
     */
    public function switch(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Switch',
            'description' => 'Network switch for connectivity',
            'default_u_size' => 1,
        ]);
    }

    /**
     * Indicate a storage device type.
     */
    public function storage(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Storage',
            'description' => 'Storage array or NAS device',
            'default_u_size' => 4,
        ]);
    }

    /**
     * Set a specific default U size for the device type.
     */
    public function withDefaultUSize(float|int $uSize): static
    {
        return $this->state(fn (array $attributes) => [
            'default_u_size' => $uSize,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\CapacitySnapshot;
use App\Models\Datacenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapacitySnapshot>
 */
class CapacitySnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'datacenter_id' => Datacenter::factory(),
            'snapshot_date' => $this->faker->dateTimeBetween('-52 weeks', 'now')->format('Y-m-d'),
            'rack_utilization_percent' => $this->faker->randomFloat(2, 0, 100),
            'power_utilization_percent' => $this->faker->optional(0.8)->randomFloat(2, 0, 100),
            'total_u_space' => $this->faker->numberBetween(100, 2000),
            'used_u_space' => function (array $attributes) {
                return (int) ($attributes['total_u_space'] * ($attributes['rack_utilization_percent'] / 100));
            },
            'total_power_capacity' => $this->faker->optional(0.8)->numberBetween(50000, 500000),
            'total_power_consumption' => function (array $attributes) {
                if ($attributes['total_power_capacity'] === null) {
                    return null;
                }

                return (int) ($attributes['total_power_capacity'] * (($attributes['power_utilization_percent'] ?? 50) / 100));
            },
            'port_stats' => [
                'ethernet' => [
                    'total_ports' => $this->faker->numberBetween(50, 500),
                    'connected_ports' => $this->faker->numberBetween(20, 300),
                    'available_ports' => $this->faker->numberBetween(10, 200),
                ],
                'fiber' => [
                    'total_ports' => $this->faker->numberBetween(10, 100),
                    'connected_ports' => $this->faker->numberBetween(5, 50),
                    'available_ports' => $this->faker->numberBetween(5, 50),
                ],
                'power' => [
                    'total_ports' => $this->faker->numberBetween(20, 200),
                    'connected_ports' => $this->faker->numberBetween(10, 100),
                    'available_ports' => $this->faker->numberBetween(10, 100),
                ],
            ],
            'device_count' => $this->faker->numberBetween(10, 500),
        ];
    }

    /**
     * Indicate a specific snapshot date.
     */
    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'snapshot_date' => $date,
        ]);
    }

    /**
     * Indicate high utilization (above 80%).
     */
    public function highUtilization(): static
    {
        return $this->state(fn (array $attributes) => [
            'rack_utilization_percent' => $this->faker->randomFloat(2, 80, 95),
            'power_utilization_percent' => $this->faker->randomFloat(2, 75, 90),
        ]);
    }

    /**
     * Indicate low utilization (below 50%).
     */
    public function lowUtilization(): static
    {
        return $this->state(fn (array $attributes) => [
            'rack_utilization_percent' => $this->faker->randomFloat(2, 10, 50),
            'power_utilization_percent' => $this->faker->randomFloat(2, 10, 45),
        ]);
    }

    /**
     * Indicate no power data configured.
     */
    public function withoutPowerData(): static
    {
        return $this->state(fn (array $attributes) => [
            'power_utilization_percent' => null,
            'total_power_capacity' => null,
            'total_power_consumption' => null,
        ]);
    }

    /**
     * Indicate a specific device count.
     */
    public function withDeviceCount(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'device_count' => $count,
        ]);
    }

    /**
     * Indicate no device count data (for backward compatibility with older records).
     */
    public function withoutDeviceCount(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_count' => null,
        ]);
    }
}

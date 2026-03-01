<?php

namespace Database\Factories;

use App\Enums\DeviceDepth;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Device test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Device::class;

    /**
     * Realistic device names for datacenter equipment.
     *
     * @var array<string>
     */
    private array $deviceNames = [
        'Web Server',
        'Database Server',
        'Application Server',
        'File Server',
        'Mail Server',
        'DNS Server',
        'DHCP Server',
        'Proxy Server',
        'Backup Server',
        'Monitoring Server',
        'Core Switch',
        'Access Switch',
        'Distribution Switch',
        'Edge Router',
        'Core Router',
        'Storage Array',
        'NAS Device',
        'SAN Controller',
        'Tape Library',
        'UPS Unit',
        'PDU',
        'Patch Panel',
        'KVM Switch',
        'Console Server',
        'Firewall',
        'Load Balancer',
    ];

    /**
     * Device manufacturers.
     *
     * @var array<string>
     */
    private array $manufacturers = [
        'Dell',
        'HP',
        'Cisco',
        'Juniper',
        'IBM',
        'Lenovo',
        'Supermicro',
        'NetApp',
        'EMC',
        'APC',
        'Eaton',
        'Arista',
        'Fortinet',
        'F5',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseName = fake()->randomElement($this->deviceNames);
        $number = str_pad((string) fake()->numberBetween(1, 99), 2, '0', STR_PAD_LEFT);

        return [
            'name' => "{$baseName} {$number}",
            'device_type_id' => DeviceType::factory(),
            'lifecycle_status' => DeviceLifecycleStatus::InStock,
            'serial_number' => fake()->optional(0.8)->bothify('SN-####-????-####'),
            'manufacturer' => fake()->optional(0.7)->randomElement($this->manufacturers),
            'model' => fake()->optional(0.6)->bothify('Model-???-####'),
            'purchase_date' => fake()->optional(0.5)->dateTimeBetween('-3 years', 'now'),
            'warranty_start_date' => fake()->optional(0.4)->dateTimeBetween('-3 years', 'now'),
            'warranty_end_date' => fake()->optional(0.4)->dateTimeBetween('now', '+3 years'),
            'u_height' => fake()->numberBetween(1, 4),
            'depth' => DeviceDepth::Standard,
            'width_type' => DeviceWidthType::Full,
            'rack_face' => DeviceRackFace::Front,
            'rack_id' => null,
            'start_u' => null,
            'specs' => $this->generateSpecs(),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Generate realistic device specifications.
     *
     * @return array<string, mixed>|null
     */
    private function generateSpecs(): ?array
    {
        if (fake()->boolean(70)) {
            return [
                'cpu_count' => fake()->numberBetween(1, 4),
                'ram_gb' => fake()->randomElement([8, 16, 32, 64, 128, 256]),
                'storage_tb' => fake()->randomElement([0.5, 1, 2, 4, 8, 16]),
                'power_watts' => fake()->numberBetween(200, 2000),
            ];
        }

        return null;
    }

    /**
     * Set device lifecycle status to ordered.
     */
    public function ordered(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => DeviceLifecycleStatus::Ordered,
            'rack_id' => null,
            'start_u' => null,
        ]);
    }

    /**
     * Set device lifecycle status to received.
     */
    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => DeviceLifecycleStatus::Received,
            'rack_id' => null,
            'start_u' => null,
        ]);
    }

    /**
     * Set device lifecycle status to in stock.
     */
    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => DeviceLifecycleStatus::InStock,
            'rack_id' => null,
            'start_u' => null,
        ]);
    }

    /**
     * Set device lifecycle status to deployed.
     */
    public function deployed(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        ]);
    }

    /**
     * Set device lifecycle status to maintenance.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => DeviceLifecycleStatus::Maintenance,
        ]);
    }

    /**
     * Set device lifecycle status to decommissioned.
     */
    public function decommissioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => DeviceLifecycleStatus::Decommissioned,
            'rack_id' => null,
            'start_u' => null,
        ]);
    }

    /**
     * Set device lifecycle status to disposed.
     */
    public function disposed(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => DeviceLifecycleStatus::Disposed,
            'rack_id' => null,
            'start_u' => null,
        ]);
    }

    /**
     * Create an unplaced device (in inventory, not in a rack).
     */
    public function unplaced(): static
    {
        return $this->state(fn (array $attributes) => [
            'rack_id' => null,
            'start_u' => null,
        ]);
    }

    /**
     * Create a placed device in a specific rack at a specific position.
     */
    public function placed(?Rack $rack = null, ?int $startU = null): static
    {
        return $this->state(function (array $attributes) use ($rack, $startU) {
            return [
                'rack_id' => $rack?->id ?? Rack::factory(),
                'start_u' => $startU ?? fake()->numberBetween(1, 40),
                'lifecycle_status' => DeviceLifecycleStatus::Deployed,
            ];
        });
    }

    /**
     * Set a specific U height for the device.
     */
    public function withUHeight(float|int $uHeight): static
    {
        return $this->state(fn (array $attributes) => [
            'u_height' => $uHeight,
        ]);
    }

    /**
     * Set device to half-width left position.
     */
    public function halfLeft(): static
    {
        return $this->state(fn (array $attributes) => [
            'width_type' => DeviceWidthType::HalfLeft,
        ]);
    }

    /**
     * Set device to half-width right position.
     */
    public function halfRight(): static
    {
        return $this->state(fn (array $attributes) => [
            'width_type' => DeviceWidthType::HalfRight,
        ]);
    }

    /**
     * Set device for rear rack placement.
     */
    public function rear(): static
    {
        return $this->state(fn (array $attributes) => [
            'rack_face' => DeviceRackFace::Rear,
        ]);
    }

    /**
     * Set device with warranty information.
     */
    public function withWarranty(?\DateTimeInterface $start = null, ?\DateTimeInterface $end = null): static
    {
        return $this->state(function (array $attributes) use ($start, $end) {
            $warrantyStart = $start ?? now()->subYear();
            $warrantyEnd = $end ?? now()->addYears(2);

            return [
                'warranty_start_date' => $warrantyStart,
                'warranty_end_date' => $warrantyEnd,
            ];
        });
    }
}

<?php

namespace Database\Factories;

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\PortVisualFace;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Port test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Port>
 */
class PortFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Port::class;

    /**
     * Define the model's default state.
     *
     * Default generates an Ethernet port with consistent type/subtype.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = PortType::Ethernet;
        $subtypes = PortSubtype::forType($type);
        $subtype = fake()->randomElement($subtypes);
        $direction = PortDirection::defaultForType($type);

        return [
            'device_id' => Device::factory(),
            'label' => 'eth' . fake()->unique()->numberBetween(0, 9999),
            'type' => $type,
            'subtype' => $subtype,
            'status' => PortStatus::Available,
            'direction' => $direction,
            'position_slot' => null,
            'position_row' => null,
            'position_column' => null,
            'visual_x' => null,
            'visual_y' => null,
            'visual_face' => null,
        ];
    }

    /**
     * Create an Ethernet port.
     */
    public function ethernet(): static
    {
        return $this->state(function (array $attributes) {
            $subtypes = PortSubtype::forType(PortType::Ethernet);

            return [
                'type' => PortType::Ethernet,
                'subtype' => fake()->randomElement($subtypes),
                'direction' => PortDirection::defaultForType(PortType::Ethernet),
                'label' => 'eth' . fake()->unique()->numberBetween(0, 9999),
            ];
        });
    }

    /**
     * Create a Fiber port.
     */
    public function fiber(): static
    {
        return $this->state(function (array $attributes) {
            $subtypes = PortSubtype::forType(PortType::Fiber);

            return [
                'type' => PortType::Fiber,
                'subtype' => fake()->randomElement($subtypes),
                'direction' => PortDirection::defaultForType(PortType::Fiber),
                'label' => 'fiber' . fake()->unique()->numberBetween(0, 9999),
            ];
        });
    }

    /**
     * Create a Power port.
     */
    public function power(): static
    {
        return $this->state(function (array $attributes) {
            $subtypes = PortSubtype::forType(PortType::Power);

            return [
                'type' => PortType::Power,
                'subtype' => fake()->randomElement($subtypes),
                'direction' => PortDirection::defaultForType(PortType::Power),
                'label' => 'psu' . fake()->unique()->numberBetween(0, 9999),
            ];
        });
    }

    /**
     * Set port status to available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PortStatus::Available,
        ]);
    }

    /**
     * Set port status to connected.
     */
    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PortStatus::Connected,
        ]);
    }

    /**
     * Set port status to reserved.
     */
    public function reserved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PortStatus::Reserved,
        ]);
    }

    /**
     * Set port status to disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PortStatus::Disabled,
        ]);
    }

    /**
     * Create a 1GbE Ethernet port.
     */
    public function gbe1(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PortType::Ethernet,
            'subtype' => PortSubtype::Gbe1,
            'direction' => PortDirection::Bidirectional,
        ]);
    }

    /**
     * Create a 10GbE Ethernet port.
     */
    public function gbe10(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PortType::Ethernet,
            'subtype' => PortSubtype::Gbe10,
            'direction' => PortDirection::Bidirectional,
        ]);
    }

    /**
     * Create a port with visual position data.
     */
    public function withVisualPosition(?float $x = null, ?float $y = null, ?PortVisualFace $face = null): static
    {
        return $this->state(fn (array $attributes) => [
            'visual_x' => $x ?? fake()->randomFloat(2, 0, 100),
            'visual_y' => $y ?? fake()->randomFloat(2, 0, 100),
            'visual_face' => $face ?? fake()->randomElement(PortVisualFace::cases()),
        ]);
    }

    /**
     * Create a port with physical position data.
     */
    public function withPosition(?int $slot = null, ?int $row = null, ?int $column = null): static
    {
        return $this->state(fn (array $attributes) => [
            'position_slot' => $slot ?? fake()->numberBetween(1, 4),
            'position_row' => $row ?? fake()->numberBetween(1, 4),
            'position_column' => $column ?? fake()->numberBetween(1, 12),
        ]);
    }

    /**
     * Create an uplink port.
     */
    public function uplink(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => PortDirection::Uplink,
        ]);
    }

    /**
     * Create a downlink port.
     */
    public function downlink(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => PortDirection::Downlink,
        ]);
    }
}

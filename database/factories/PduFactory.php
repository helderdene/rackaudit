<?php

namespace Database\Factories;

use App\Enums\PduPhase;
use App\Enums\PduStatus;
use App\Models\Pdu;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Pdu test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pdu>
 */
class PduFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Pdu::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'PDU-'.fake()->unique()->numberBetween(1000, 9999),
            'model' => fake()->optional()->randomElement(['APC AP8941', 'Eaton G3', 'Raritan PX3', 'CyberPower PDU41001']),
            'manufacturer' => fake()->optional()->randomElement(['APC', 'Eaton', 'Raritan', 'CyberPower', 'Vertiv']),
            'total_capacity_kw' => fake()->optional()->randomFloat(2, 5, 50),
            'voltage' => fake()->optional()->randomElement([120, 208, 240, 480]),
            'phase' => fake()->randomElement(PduPhase::cases()),
            'circuit_count' => fake()->numberBetween(12, 48),
            'status' => PduStatus::Active,
            'room_id' => Room::factory(),
            'row_id' => null,
        ];
    }

    /**
     * Indicate that the PDU is single phase.
     */
    public function singlePhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'phase' => PduPhase::Single,
            'voltage' => fake()->randomElement([120, 208, 240]),
        ]);
    }

    /**
     * Indicate that the PDU is three phase.
     */
    public function threePhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'phase' => PduPhase::ThreePhase,
            'voltage' => fake()->randomElement([208, 480]),
            'total_capacity_kw' => fake()->randomFloat(2, 20, 100),
        ]);
    }

    /**
     * Indicate that the PDU is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PduStatus::Active,
        ]);
    }

    /**
     * Indicate that the PDU is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PduStatus::Inactive,
        ]);
    }

    /**
     * Indicate that the PDU is under maintenance.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PduStatus::Maintenance,
        ]);
    }

    /**
     * Indicate that the PDU is assigned at room level.
     */
    public function roomLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'room_id' => Room::factory(),
            'row_id' => null,
        ]);
    }

    /**
     * Indicate that the PDU is assigned at row level.
     */
    public function rowLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'room_id' => null,
            'row_id' => Row::factory(),
        ]);
    }

    /**
     * Indicate that the PDU has full specifications.
     */
    public function withFullSpecs(): static
    {
        return $this->state(fn (array $attributes) => [
            'model' => 'APC AP8941',
            'manufacturer' => 'APC',
            'total_capacity_kw' => 17.3,
            'voltage' => 208,
        ]);
    }
}

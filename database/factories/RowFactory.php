<?php

namespace Database\Factories;

use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Row test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Row>
 */
class RowFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Row::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Row '.fake()->unique()->randomLetter().fake()->numberBetween(1, 99),
            'position' => fake()->numberBetween(1, 20),
            'orientation' => fake()->randomElement(RowOrientation::cases()),
            'status' => RowStatus::Active,
            'room_id' => Room::factory(),
        ];
    }

    /**
     * Indicate that the row has hot aisle orientation.
     */
    public function hotAisle(): static
    {
        return $this->state(fn (array $attributes) => [
            'orientation' => RowOrientation::HotAisle,
        ]);
    }

    /**
     * Indicate that the row has cold aisle orientation.
     */
    public function coldAisle(): static
    {
        return $this->state(fn (array $attributes) => [
            'orientation' => RowOrientation::ColdAisle,
        ]);
    }

    /**
     * Indicate that the row is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RowStatus::Active,
        ]);
    }

    /**
     * Indicate that the row is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RowStatus::Inactive,
        ]);
    }

    /**
     * Set a specific position for the row.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }
}

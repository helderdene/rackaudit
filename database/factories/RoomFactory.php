<?php

namespace Database\Factories;

use App\Enums\RoomType;
use App\Models\Datacenter;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Room test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Room::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Room '.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->optional()->sentence(),
            'square_footage' => fake()->optional()->randomFloat(2, 100, 10000),
            'type' => fake()->randomElement(RoomType::cases()),
            'datacenter_id' => Datacenter::factory(),
        ];
    }

    /**
     * Indicate that the room is a server room.
     */
    public function serverRoom(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RoomType::ServerRoom,
            'name' => 'Server Room '.fake()->unique()->numberBetween(1, 99),
        ]);
    }

    /**
     * Indicate that the room is a network closet.
     */
    public function networkCloset(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RoomType::NetworkCloset,
            'name' => 'Network Closet '.fake()->unique()->numberBetween(1, 99),
            'square_footage' => fake()->randomFloat(2, 50, 500),
        ]);
    }

    /**
     * Indicate that the room is a cage/colocation space.
     */
    public function cageColocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RoomType::CageColocation,
            'name' => 'Cage '.fake()->unique()->numberBetween(1, 99),
        ]);
    }

    /**
     * Indicate that the room is a storage room.
     */
    public function storage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RoomType::Storage,
            'name' => 'Storage '.fake()->unique()->numberBetween(1, 99),
            'square_footage' => fake()->randomFloat(2, 100, 1000),
        ]);
    }

    /**
     * Indicate that the room is an electrical room.
     */
    public function electricalRoom(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RoomType::ElectricalRoom,
            'name' => 'Electrical Room '.fake()->unique()->numberBetween(1, 99),
        ]);
    }

    /**
     * Indicate that the room has a description.
     */
    public function withDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => fake()->paragraph(),
        ]);
    }

    /**
     * Indicate that the room has square footage defined.
     */
    public function withSquareFootage(): static
    {
        return $this->state(fn (array $attributes) => [
            'square_footage' => fake()->randomFloat(2, 500, 5000),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\CableType;
use App\Models\Connection;
use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Connection test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Connection>
 */
class ConnectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Connection::class;

    /**
     * Define the model's default state.
     *
     * Default generates an Ethernet connection with Cat6 cable.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_port_id' => Port::factory()->ethernet(),
            'destination_port_id' => Port::factory()->ethernet(),
            'cable_type' => CableType::Cat6,
            'cable_length' => fake()->randomFloat(2, 0.5, 50),
            'cable_color' => fake()->randomElement(['blue', 'yellow', 'green', 'red', 'white', 'gray']),
            'path_notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Create a connection with Cat5e cable.
     */
    public function cat5e(): static
    {
        return $this->state(fn (array $attributes) => [
            'cable_type' => CableType::Cat5e,
        ]);
    }

    /**
     * Create a connection with Cat6 cable.
     */
    public function cat6(): static
    {
        return $this->state(fn (array $attributes) => [
            'cable_type' => CableType::Cat6,
        ]);
    }

    /**
     * Create a connection with Cat6a cable.
     */
    public function cat6a(): static
    {
        return $this->state(fn (array $attributes) => [
            'cable_type' => CableType::Cat6a,
        ]);
    }

    /**
     * Create a fiber single-mode connection.
     */
    public function fiberSm(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_port_id' => Port::factory()->fiber(),
            'destination_port_id' => Port::factory()->fiber(),
            'cable_type' => CableType::FiberSm,
        ]);
    }

    /**
     * Create a fiber multi-mode connection.
     */
    public function fiberMm(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_port_id' => Port::factory()->fiber(),
            'destination_port_id' => Port::factory()->fiber(),
            'cable_type' => CableType::FiberMm,
        ]);
    }

    /**
     * Create a power connection with C13 cable.
     */
    public function powerC13(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_port_id' => Port::factory()->power(),
            'destination_port_id' => Port::factory()->power(),
            'cable_type' => CableType::PowerC13,
        ]);
    }

    /**
     * Create a power connection with C14 cable.
     */
    public function powerC14(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_port_id' => Port::factory()->power(),
            'destination_port_id' => Port::factory()->power(),
            'cable_type' => CableType::PowerC14,
        ]);
    }

    /**
     * Create a power connection with C19 cable.
     */
    public function powerC19(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_port_id' => Port::factory()->power(),
            'destination_port_id' => Port::factory()->power(),
            'cable_type' => CableType::PowerC19,
        ]);
    }

    /**
     * Create a power connection with C20 cable.
     */
    public function powerC20(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_port_id' => Port::factory()->power(),
            'destination_port_id' => Port::factory()->power(),
            'cable_type' => CableType::PowerC20,
        ]);
    }

    /**
     * Create a connection without path notes.
     */
    public function withoutNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'path_notes' => null,
        ]);
    }

    /**
     * Create a connection with specific path notes.
     */
    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'path_notes' => $notes,
        ]);
    }
}

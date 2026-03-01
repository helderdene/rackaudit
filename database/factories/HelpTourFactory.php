<?php

namespace Database\Factories;

use App\Models\HelpTour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for generating HelpTour test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HelpTour>
 */
class HelpTourFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = HelpTour::class;

    /**
     * Define the model's default state.
     *
     * Default generates an active tour.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true) . ' Tour';

        return [
            'slug' => Str::slug($name) . '-' . fake()->unique()->randomNumber(5),
            'name' => ucwords($name),
            'context_key' => fake()->optional(0.7)->randomElement([
                'audits.execute',
                'audits.execute.connection',
                'audits.execute.inventory',
                'implementations.files',
                'racks.elevation',
            ]),
            'description' => fake()->optional(0.8)->paragraph(),
            'is_active' => true,
        ];
    }

    /**
     * Create an inactive tour.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a tour for a specific context key.
     */
    public function forContextKey(string $contextKey): static
    {
        return $this->state(fn (array $attributes) => [
            'context_key' => $contextKey,
        ]);
    }

    /**
     * Create a tour with a specific slug.
     */
    public function withSlug(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => $slug,
        ]);
    }

    /**
     * Create a tour with a specific name.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}

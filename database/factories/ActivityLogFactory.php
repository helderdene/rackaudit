<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Create user without triggering events to avoid cascade of activity logs
        $user = User::withoutEvents(fn () => User::factory()->create());

        return [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_id' => fn () => User::withoutEvents(fn () => User::factory()->create())->id,
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'old_values' => null,
            'new_values' => ['name' => fake()->name(), 'email' => fake()->safeEmail()],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate that the activity log is for a created action.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'created',
            'old_values' => null,
            'new_values' => ['name' => fake()->name(), 'email' => fake()->safeEmail()],
        ]);
    }

    /**
     * Indicate that the activity log is for an updated action.
     */
    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'updated',
            'old_values' => ['name' => fake()->name()],
            'new_values' => ['name' => fake()->name()],
        ]);
    }

    /**
     * Indicate that the activity log is for a deleted action.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'deleted',
            'old_values' => ['name' => fake()->name(), 'email' => fake()->safeEmail()],
            'new_values' => null,
        ]);
    }
}

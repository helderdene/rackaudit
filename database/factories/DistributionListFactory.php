<?php

namespace Database\Factories;

use App\Models\DistributionList;
use App\Models\DistributionListMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating DistributionList test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DistributionList>
 */
class DistributionListFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = DistributionList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional(0.7)->sentence(),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Create a distribution list with a specified number of members.
     */
    public function withMembers(int $count = 3): static
    {
        return $this->afterCreating(function (DistributionList $distributionList) use ($count) {
            DistributionListMember::factory()
                ->count($count)
                ->sequence(fn ($sequence) => ['sort_order' => $sequence->index + 1])
                ->create(['distribution_list_id' => $distributionList->id]);
        });
    }

    /**
     * Create an empty distribution list with no members.
     */
    public function empty(): static
    {
        return $this;
    }

    /**
     * Create a distribution list for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a distribution list with a specific name.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Create a distribution list with a specific description.
     */
    public function withDescription(string $description): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $description,
        ]);
    }
}

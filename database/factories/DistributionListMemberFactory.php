<?php

namespace Database\Factories;

use App\Models\DistributionList;
use App\Models\DistributionListMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating DistributionListMember test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DistributionListMember>
 */
class DistributionListMemberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = DistributionListMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'distribution_list_id' => DistributionList::factory(),
            'email' => fake()->unique()->safeEmail(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Create a member for a specific distribution list.
     */
    public function forDistributionList(DistributionList $distributionList): static
    {
        return $this->state(fn (array $attributes) => [
            'distribution_list_id' => $distributionList->id,
        ]);
    }

    /**
     * Create a member with a specific email.
     */
    public function withEmail(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $email,
        ]);
    }

    /**
     * Create a member with a specific sort order.
     */
    public function withSortOrder(int $sortOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $sortOrder,
        ]);
    }
}

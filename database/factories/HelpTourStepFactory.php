<?php

namespace Database\Factories;

use App\Enums\HelpTourStepPosition;
use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating HelpTourStep test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HelpTourStep>
 */
class HelpTourStepFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = HelpTourStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'help_tour_id' => HelpTour::factory(),
            'help_article_id' => HelpArticle::factory()->tourStep(),
            'target_selector' => fake()->randomElement([
                '#main-content',
                '.sidebar-nav',
                '[data-tour-target="start"]',
                '.btn-primary',
                '#create-form',
                '.data-table',
            ]),
            'position' => fake()->randomElement([
                HelpTourStepPosition::Top,
                HelpTourStepPosition::Right,
                HelpTourStepPosition::Bottom,
                HelpTourStepPosition::Left,
            ]),
            'step_order' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Create a step for a specific tour.
     */
    public function forTour(HelpTour $tour): static
    {
        return $this->state(fn (array $attributes) => [
            'help_tour_id' => $tour->id,
        ]);
    }

    /**
     * Create a step with a specific article.
     */
    public function withArticle(HelpArticle $article): static
    {
        return $this->state(fn (array $attributes) => [
            'help_article_id' => $article->id,
        ]);
    }

    /**
     * Create a step with a specific position.
     */
    public function position(HelpTourStepPosition $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }

    /**
     * Create a step with a specific order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'step_order' => $order,
        ]);
    }

    /**
     * Create a step with a specific target selector.
     */
    public function targeting(string $selector): static
    {
        return $this->state(fn (array $attributes) => [
            'target_selector' => $selector,
        ]);
    }
}

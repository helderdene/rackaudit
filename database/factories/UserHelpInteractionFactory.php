<?php

namespace Database\Factories;

use App\Enums\HelpInteractionType;
use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\User;
use App\Models\UserHelpInteraction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating UserHelpInteraction test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserHelpInteraction>
 */
class UserHelpInteractionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = UserHelpInteraction::class;

    /**
     * Define the model's default state.
     *
     * Default generates a viewed interaction for an article.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'help_article_id' => HelpArticle::factory(),
            'help_tour_id' => null,
            'interaction_type' => HelpInteractionType::Viewed,
        ];
    }

    /**
     * Create a viewed interaction.
     */
    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'interaction_type' => HelpInteractionType::Viewed,
        ]);
    }

    /**
     * Create a dismissed interaction.
     */
    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'interaction_type' => HelpInteractionType::Dismissed,
        ]);
    }

    /**
     * Create a completed tour interaction.
     *
     * Only sets help_tour_id to a new factory if one isn't already set via forTour().
     */
    public function completedTour(): static
    {
        return $this->state(fn (array $attributes) => [
            'help_article_id' => null,
            'help_tour_id' => $attributes['help_tour_id'] ?? HelpTour::factory(),
            'interaction_type' => HelpInteractionType::CompletedTour,
        ]);
    }

    /**
     * Create an interaction for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create an interaction for a specific article.
     */
    public function forArticle(HelpArticle $article): static
    {
        return $this->state(fn (array $attributes) => [
            'help_article_id' => $article->id,
            'help_tour_id' => null,
        ]);
    }

    /**
     * Create an interaction for a specific tour.
     */
    public function forTour(HelpTour $tour): static
    {
        return $this->state(fn (array $attributes) => [
            'help_article_id' => null,
            'help_tour_id' => $tour->id,
        ]);
    }
}

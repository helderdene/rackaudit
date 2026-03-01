<?php

namespace Database\Factories;

use App\Enums\HelpArticleType;
use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for generating HelpArticle test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HelpArticle>
 */
class HelpArticleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = HelpArticle::class;

    /**
     * Define the model's default state.
     *
     * Default generates an active article with random type.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->randomNumber(5),
            'title' => $title,
            'content' => $this->generateMarkdownContent(),
            'context_key' => fake()->optional(0.7)->randomElement([
                'audits.execute',
                'audits.execute.connection',
                'audits.execute.inventory',
                'implementations.files',
                'racks.elevation',
                'connections.create',
                'devices.create',
            ]),
            'article_type' => fake()->randomElement([
                HelpArticleType::Tooltip,
                HelpArticleType::TourStep,
                HelpArticleType::Article,
            ]),
            'category' => fake()->optional(0.8)->randomElement([
                'Getting Started',
                'Audits',
                'Connections',
                'Devices',
                'Racks',
                'Administration',
            ]),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    /**
     * Generate sample markdown content for help articles.
     */
    protected function generateMarkdownContent(): string
    {
        $paragraphs = fake()->paragraphs(fake()->numberBetween(2, 4));
        $content = "## Overview\n\n";
        $content .= $paragraphs[0]."\n\n";

        if (count($paragraphs) > 1) {
            $content .= "### Key Points\n\n";
            $content .= '- '.fake()->sentence()."\n";
            $content .= '- '.fake()->sentence()."\n";
            $content .= '- '.fake()->sentence()."\n\n";
            $content .= $paragraphs[1]."\n";
        }

        return $content;
    }

    /**
     * Create a tooltip article.
     */
    public function tooltip(): static
    {
        return $this->state(fn (array $attributes) => [
            'article_type' => HelpArticleType::Tooltip,
            'content' => fake()->paragraph(),
        ]);
    }

    /**
     * Create a tour step article.
     */
    public function tourStep(): static
    {
        return $this->state(fn (array $attributes) => [
            'article_type' => HelpArticleType::TourStep,
            'content' => "**Step Instructions**\n\n".fake()->paragraph(),
        ]);
    }

    /**
     * Create a full article.
     */
    public function article(): static
    {
        return $this->state(fn (array $attributes) => [
            'article_type' => HelpArticleType::Article,
            'content' => $this->generateMarkdownContent(),
        ]);
    }

    /**
     * Create an inactive article.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create an article for a specific context key.
     */
    public function forContextKey(string $contextKey): static
    {
        return $this->state(fn (array $attributes) => [
            'context_key' => $contextKey,
        ]);
    }

    /**
     * Create an article in a specific category.
     */
    public function inCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Create an article with a specific slug.
     */
    public function withSlug(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => $slug,
        ]);
    }

    /**
     * Create an article with a specific title.
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $title,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\FindingStatusTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating FindingStatusTransition test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FindingStatusTransition>
 */
class FindingStatusTransitionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = FindingStatusTransition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finding_id' => Finding::factory(),
            'from_status' => FindingStatus::Open,
            'to_status' => FindingStatus::InProgress,
            'user_id' => User::factory(),
            'notes' => null,
            'transitioned_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Create a transition from Open to InProgress.
     */
    public function startWorking(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => FindingStatus::Open,
            'to_status' => FindingStatus::InProgress,
        ]);
    }

    /**
     * Create a transition from InProgress to PendingReview.
     */
    public function submitForReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => FindingStatus::InProgress,
            'to_status' => FindingStatus::PendingReview,
        ]);
    }

    /**
     * Create a transition from PendingReview to Resolved.
     */
    public function resolve(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => FindingStatus::PendingReview,
            'to_status' => FindingStatus::Resolved,
            'notes' => fake()->sentence(),
        ]);
    }

    /**
     * Create a transition to Deferred.
     */
    public function defer(?FindingStatus $fromStatus = null): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => $fromStatus ?? FindingStatus::Open,
            'to_status' => FindingStatus::Deferred,
        ]);
    }

    /**
     * Create a transition from Deferred to Open.
     */
    public function reopen(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => FindingStatus::Deferred,
            'to_status' => FindingStatus::Open,
        ]);
    }

    /**
     * Create a transition for a specific finding.
     */
    public function forFinding(Finding $finding): static
    {
        return $this->state(fn (array $attributes) => [
            'finding_id' => $finding->id,
        ]);
    }

    /**
     * Create a transition by a specific user.
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a transition with notes.
     */
    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }

    /**
     * Create a transition at a specific time.
     */
    public function transitionedAt(\DateTimeInterface $dateTime): static
    {
        return $this->state(fn (array $attributes) => [
            'transitioned_at' => $dateTime,
        ]);
    }
}

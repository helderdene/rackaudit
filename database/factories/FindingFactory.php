<?php

namespace Database\Factories;

use App\Enums\DiscrepancyType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\Finding;
use App\Models\FindingCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Finding test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Finding>
 */
class FindingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Finding::class;

    /**
     * Define the model's default state.
     *
     * Default generates an open finding with a random discrepancy type.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_id' => Audit::factory(),
            'audit_connection_verification_id' => AuditConnectionVerification::factory()->discrepant(),
            'discrepancy_type' => fake()->randomElement([
                DiscrepancyType::Missing,
                DiscrepancyType::Unexpected,
                DiscrepancyType::Mismatched,
                DiscrepancyType::Conflicting,
            ]),
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.7)->paragraph(),
            'status' => FindingStatus::Open,
            'severity' => FindingSeverity::Medium,
            'assigned_to' => null,
            'finding_category_id' => null,
            'resolution_notes' => null,
            'resolved_by' => null,
            'resolved_at' => null,
            'due_date' => null,
        ];
    }

    /**
     * Create a finding with open status.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::Open,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);
    }

    /**
     * Create a finding with in progress status.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::InProgress,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);
    }

    /**
     * Create a finding with pending review status.
     */
    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::PendingReview,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);
    }

    /**
     * Create a finding with deferred status.
     */
    public function deferred(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::Deferred,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);
    }

    /**
     * Create a finding with resolved status.
     */
    public function resolved(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::Resolved,
            'resolution_notes' => fake()->paragraph(),
            'resolved_by' => $user?->id ?? User::factory(),
            'resolved_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create a finding with critical severity.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::Critical,
        ]);
    }

    /**
     * Create a finding with high severity.
     */
    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::High,
        ]);
    }

    /**
     * Create a finding with medium severity.
     */
    public function medium(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::Medium,
        ]);
    }

    /**
     * Create a finding with low severity.
     */
    public function low(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::Low,
        ]);
    }

    /**
     * Create a finding for a missing connection discrepancy.
     */
    public function missing(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancy_type' => DiscrepancyType::Missing,
        ]);
    }

    /**
     * Create a finding for an unexpected connection discrepancy.
     */
    public function unexpected(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancy_type' => DiscrepancyType::Unexpected,
        ]);
    }

    /**
     * Create a finding for a mismatched connection discrepancy.
     */
    public function mismatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancy_type' => DiscrepancyType::Mismatched,
        ]);
    }

    /**
     * Create a finding for a conflicting connection discrepancy.
     */
    public function conflicting(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancy_type' => DiscrepancyType::Conflicting,
        ]);
    }

    /**
     * Create a finding for a specific audit.
     */
    public function forAudit(Audit $audit): static
    {
        return $this->state(fn (array $attributes) => [
            'audit_id' => $audit->id,
        ]);
    }

    /**
     * Create a finding for a specific verification.
     */
    public function forVerification(AuditConnectionVerification $verification): static
    {
        return $this->state(fn (array $attributes) => [
            'audit_id' => $verification->audit_id,
            'audit_connection_verification_id' => $verification->id,
        ]);
    }

    /**
     * Create a finding with a specific title.
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $title,
        ]);
    }

    /**
     * Create a finding with a specific description.
     */
    public function withDescription(string $description): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $description,
        ]);
    }

    /**
     * Create a finding assigned to a specific user.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $user->id,
        ]);
    }

    /**
     * Create a finding with a specific category.
     */
    public function withCategory(FindingCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'finding_category_id' => $category->id,
        ]);
    }

    /**
     * Create a finding with resolution notes.
     */
    public function withResolutionNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Create a finding that is overdue (past due date).
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => fake()->dateTimeBetween('-2 weeks', '-1 day'),
        ]);
    }

    /**
     * Create a finding that is due soon (within 3 days).
     */
    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => fake()->dateTimeBetween('now', '+3 days'),
        ]);
    }

    /**
     * Create a finding with no due date set.
     */
    public function noDueDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => null,
        ]);
    }

    /**
     * Create a finding with a specific due date.
     */
    public function withDueDate(\DateTimeInterface|string $dueDate): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $dueDate,
        ]);
    }
}

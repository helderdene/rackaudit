<?php

namespace Database\Factories;

use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Audit test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Audit>
 */
class AuditFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Audit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Audit',
            'description' => fake()->optional(0.7)->sentence(10),
            'due_date' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'type' => AuditType::Connection,
            'scope_type' => AuditScopeType::Datacenter,
            'status' => AuditStatus::Pending,
            'datacenter_id' => Datacenter::factory(),
            'room_id' => null,
            'implementation_file_id' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the audit is a connection audit.
     */
    public function connectionType(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AuditType::Connection,
        ]);
    }

    /**
     * Indicate that the audit is an inventory audit.
     */
    public function inventoryType(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AuditType::Inventory,
        ]);
    }

    /**
     * Indicate that the audit has datacenter-level scope.
     */
    public function datacenterScope(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_type' => AuditScopeType::Datacenter,
            'room_id' => null,
        ]);
    }

    /**
     * Indicate that the audit has room-level scope.
     */
    public function roomScope(?Room $room = null): static
    {
        return $this->state(function (array $attributes) use ($room) {
            if ($room) {
                return [
                    'scope_type' => AuditScopeType::Room,
                    'datacenter_id' => $room->datacenter_id,
                    'room_id' => $room->id,
                ];
            }

            return [
                'scope_type' => AuditScopeType::Room,
            ];
        });
    }

    /**
     * Indicate that the audit has racks-level scope.
     */
    public function racksScope(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_type' => AuditScopeType::Racks,
        ]);
    }

    /**
     * Indicate that the audit is pending (default state).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AuditStatus::Pending,
        ]);
    }

    /**
     * Indicate that the audit is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AuditStatus::InProgress,
        ]);
    }

    /**
     * Indicate that the audit is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AuditStatus::Completed,
        ]);
    }

    /**
     * Indicate that the audit is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AuditStatus::Cancelled,
        ]);
    }

    /**
     * Attach assignees to the audit after creation.
     */
    public function withAssignees(int $count = 1): static
    {
        return $this->afterCreating(function (Audit $audit) use ($count) {
            $users = User::factory()->count($count)->create();
            $audit->assignees()->attach($users->pluck('id'));
        });
    }
}

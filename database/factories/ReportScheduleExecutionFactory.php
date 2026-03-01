<?php

namespace Database\Factories;

use App\Models\ReportSchedule;
use App\Models\ReportScheduleExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating ReportScheduleExecution test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportScheduleExecution>
 */
class ReportScheduleExecutionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ReportScheduleExecution::class;

    /**
     * Define the model's default state.
     *
     * Default creates a pending execution.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_schedule_id' => ReportSchedule::factory(),
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
            'file_size_bytes' => null,
            'recipients_count' => null,
        ];
    }

    /**
     * Create a pending execution.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'started_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            'completed_at' => null,
            'error_message' => null,
            'file_size_bytes' => null,
            'recipients_count' => null,
        ]);
    }

    /**
     * Create a successful execution.
     */
    public function success(): static
    {
        $startedAt = fake()->dateTimeBetween('-1 week', 'now');
        $completedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(5, 120).' seconds');

        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'error_message' => null,
            'file_size_bytes' => fake()->numberBetween(10240, 10240000),
            'recipients_count' => fake()->numberBetween(1, 20),
        ]);
    }

    /**
     * Create a failed execution.
     */
    public function failed(): static
    {
        $startedAt = fake()->dateTimeBetween('-1 week', 'now');
        $completedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(1, 30).' seconds');

        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'error_message' => fake()->randomElement([
                'Connection timeout while generating report',
                'Failed to send email: SMTP connection refused',
                'Report file exceeded maximum attachment size',
                'Distribution list has no valid recipients',
                'Database query timeout',
            ]),
            'file_size_bytes' => null,
            'recipients_count' => null,
        ]);
    }

    /**
     * Create an execution for a specific schedule.
     */
    public function forSchedule(ReportSchedule $schedule): static
    {
        return $this->state(fn (array $attributes) => [
            'report_schedule_id' => $schedule->id,
        ]);
    }

    /**
     * Create an execution with a specific error message.
     */
    public function withError(string $message): static
    {
        return $this->failed()->state(fn (array $attributes) => [
            'error_message' => $message,
        ]);
    }

    /**
     * Create an execution with specific metrics.
     */
    public function withMetrics(int $fileSizeBytes, int $recipientsCount): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size_bytes' => $fileSizeBytes,
            'recipients_count' => $recipientsCount,
        ]);
    }
}

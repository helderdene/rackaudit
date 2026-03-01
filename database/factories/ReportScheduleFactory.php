<?php

namespace Database\Factories;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Enums\ScheduleFrequency;
use App\Models\DistributionList;
use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating ReportSchedule test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportSchedule>
 */
class ReportScheduleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ReportSchedule::class;

    /**
     * Define the model's default state.
     *
     * Default creates an enabled daily schedule for a capacity report.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Report',
            'user_id' => User::factory(),
            'distribution_list_id' => DistributionList::factory(),
            'report_type' => fake()->randomElement([
                ReportType::Capacity,
                ReportType::Assets,
                ReportType::Connections,
                ReportType::AuditHistory,
            ]),
            'report_configuration' => [
                'columns' => ['device_name', 'rack_name', 'u_position'],
                'filters' => [],
                'sort' => ['column' => 'device_name', 'direction' => 'asc'],
                'group_by' => null,
            ],
            'frequency' => ScheduleFrequency::Daily,
            'day_of_week' => null,
            'day_of_month' => null,
            'time_of_day' => sprintf('%02d:00', fake()->numberBetween(6, 18)),
            'timezone' => fake()->randomElement(['UTC', 'America/New_York', 'Europe/London', 'Asia/Tokyo']),
            'format' => fake()->randomElement([ReportFormat::PDF, ReportFormat::CSV]),
            'is_enabled' => true,
            'consecutive_failures' => 0,
            'next_run_at' => fake()->dateTimeBetween('now', '+1 week'),
            'last_run_at' => null,
            'last_run_status' => null,
        ];
    }

    /**
     * Create a schedule with daily frequency.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => ScheduleFrequency::Daily,
            'day_of_week' => null,
            'day_of_month' => null,
        ]);
    }

    /**
     * Create a schedule with weekly frequency.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => ScheduleFrequency::Weekly,
            'day_of_week' => fake()->numberBetween(0, 6),
            'day_of_month' => null,
        ]);
    }

    /**
     * Create a schedule with monthly frequency.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => ScheduleFrequency::Monthly,
            'day_of_week' => null,
            'day_of_month' => (string) fake()->numberBetween(1, 28),
        ]);
    }

    /**
     * Create a schedule with monthly frequency on the last day of month.
     */
    public function monthlyLastDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => ScheduleFrequency::Monthly,
            'day_of_week' => null,
            'day_of_month' => 'last',
        ]);
    }

    /**
     * Create a disabled schedule.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    /**
     * Create a schedule that has failed multiple times.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'consecutive_failures' => fake()->numberBetween(1, 3),
            'last_run_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'last_run_status' => 'failed',
        ]);
    }

    /**
     * Create a schedule that has been disabled due to too many failures.
     */
    public function disabledDueToFailures(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
            'consecutive_failures' => ReportSchedule::MAX_CONSECUTIVE_FAILURES,
            'last_run_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'last_run_status' => 'failed',
        ]);
    }

    /**
     * Create a schedule for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a schedule for a specific distribution list.
     */
    public function forDistributionList(DistributionList $distributionList): static
    {
        return $this->state(fn (array $attributes) => [
            'distribution_list_id' => $distributionList->id,
        ]);
    }

    /**
     * Create a schedule for a specific report type.
     */
    public function forReportType(ReportType $reportType): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => $reportType,
        ]);
    }

    /**
     * Create a schedule with a specific format.
     */
    public function withFormat(ReportFormat $format): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => $format,
        ]);
    }

    /**
     * Create a schedule with a specific timezone.
     */
    public function withTimezone(string $timezone): static
    {
        return $this->state(fn (array $attributes) => [
            'timezone' => $timezone,
        ]);
    }

    /**
     * Create a schedule with a specific time of day.
     */
    public function atTime(string $time): static
    {
        return $this->state(fn (array $attributes) => [
            'time_of_day' => $time,
        ]);
    }

    /**
     * Create a schedule with a specific report configuration.
     */
    public function withConfiguration(array $configuration): static
    {
        return $this->state(fn (array $attributes) => [
            'report_configuration' => $configuration,
        ]);
    }
}

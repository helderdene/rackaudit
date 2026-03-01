<?php

namespace Database\Factories;

use App\Models\DashboardSnapshot;
use App\Models\Datacenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating DashboardSnapshot test data.
 *
 * @extends Factory<DashboardSnapshot>
 */
class DashboardSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $criticalCount = $this->faker->numberBetween(0, 5);
        $highCount = $this->faker->numberBetween(0, 10);
        $mediumCount = $this->faker->numberBetween(0, 20);
        $lowCount = $this->faker->numberBetween(0, 30);

        return [
            'datacenter_id' => Datacenter::factory(),
            'snapshot_date' => $this->faker->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'open_findings_count' => $criticalCount + $highCount + $mediumCount + $lowCount,
            'critical_findings_count' => $criticalCount,
            'high_findings_count' => $highCount,
            'medium_findings_count' => $mediumCount,
            'low_findings_count' => $lowCount,
            'pending_audits_count' => $this->faker->numberBetween(0, 15),
            'completed_audits_count' => $this->faker->numberBetween(0, 50),
            'activity_count' => $this->faker->numberBetween(10, 200),
            'activity_by_entity' => [
                'Device' => $this->faker->numberBetween(5, 80),
                'Rack' => $this->faker->numberBetween(2, 40),
                'Connection' => $this->faker->numberBetween(3, 60),
                'Audit' => $this->faker->numberBetween(1, 20),
                'Finding' => $this->faker->numberBetween(1, 30),
            ],
        ];
    }

    /**
     * Indicate a specific snapshot date.
     */
    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'snapshot_date' => $date,
        ]);
    }

    /**
     * Indicate high severity findings (many critical/high).
     */
    public function highSeverity(): static
    {
        return $this->state(fn (array $attributes) => [
            'critical_findings_count' => $this->faker->numberBetween(5, 15),
            'high_findings_count' => $this->faker->numberBetween(10, 25),
            'medium_findings_count' => $this->faker->numberBetween(5, 15),
            'low_findings_count' => $this->faker->numberBetween(2, 10),
            'open_findings_count' => function (array $attrs) {
                return $attrs['critical_findings_count']
                    + $attrs['high_findings_count']
                    + $attrs['medium_findings_count']
                    + $attrs['low_findings_count'];
            },
        ]);
    }

    /**
     * Indicate low severity findings (mostly low/medium).
     */
    public function lowSeverity(): static
    {
        return $this->state(fn (array $attributes) => [
            'critical_findings_count' => 0,
            'high_findings_count' => $this->faker->numberBetween(0, 2),
            'medium_findings_count' => $this->faker->numberBetween(2, 10),
            'low_findings_count' => $this->faker->numberBetween(5, 20),
            'open_findings_count' => function (array $attrs) {
                return $attrs['critical_findings_count']
                    + $attrs['high_findings_count']
                    + $attrs['medium_findings_count']
                    + $attrs['low_findings_count'];
            },
        ]);
    }

    /**
     * Indicate no findings at all.
     */
    public function noFindings(): static
    {
        return $this->state(fn (array $attributes) => [
            'open_findings_count' => 0,
            'critical_findings_count' => 0,
            'high_findings_count' => 0,
            'medium_findings_count' => 0,
            'low_findings_count' => 0,
        ]);
    }

    /**
     * Indicate high activity volume.
     */
    public function highActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_count' => $this->faker->numberBetween(150, 500),
            'activity_by_entity' => [
                'Device' => $this->faker->numberBetween(50, 200),
                'Rack' => $this->faker->numberBetween(20, 100),
                'Connection' => $this->faker->numberBetween(30, 150),
                'Audit' => $this->faker->numberBetween(10, 50),
                'Finding' => $this->faker->numberBetween(10, 80),
            ],
        ]);
    }

    /**
     * Indicate no activity.
     */
    public function noActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_count' => 0,
            'activity_by_entity' => [
                'Device' => 0,
                'Rack' => 0,
                'Connection' => 0,
                'Audit' => 0,
                'Finding' => 0,
            ],
        ]);
    }

    /**
     * Indicate many pending audits.
     */
    public function manyPendingAudits(): static
    {
        return $this->state(fn (array $attributes) => [
            'pending_audits_count' => $this->faker->numberBetween(10, 30),
        ]);
    }

    /**
     * Indicate no pending audits.
     */
    public function noPendingAudits(): static
    {
        return $this->state(fn (array $attributes) => [
            'pending_audits_count' => 0,
        ]);
    }
}

<?php

namespace App\Models;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Enums\ScheduleFrequency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Report schedule for automated report generation and email delivery.
 *
 * Schedules define when reports should be generated and to whom they should
 * be delivered. Supports daily, weekly, and monthly frequencies with timezone
 * awareness. Tracks execution history and failure counts for reliability.
 */
class ReportSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\ReportScheduleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The maximum number of consecutive failures before disabling the schedule.
     */
    public const int MAX_CONSECUTIVE_FAILURES = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'user_id',
        'distribution_list_id',
        'report_type',
        'report_configuration',
        'frequency',
        'day_of_week',
        'day_of_month',
        'time_of_day',
        'timezone',
        'format',
        'is_enabled',
        'consecutive_failures',
        'next_run_at',
        'last_run_at',
        'last_run_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'frequency' => ScheduleFrequency::class,
            'format' => ReportFormat::class,
            'report_configuration' => 'array',
            'is_enabled' => 'boolean',
            'consecutive_failures' => 'integer',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this schedule.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the distribution list for this schedule.
     */
    public function distributionList(): BelongsTo
    {
        return $this->belongsTo(DistributionList::class);
    }

    /**
     * Get the execution history for this schedule.
     */
    public function executions(): HasMany
    {
        return $this->hasMany(ReportScheduleExecution::class)->orderByDesc('created_at');
    }

    /**
     * Calculate the next execution time based on the schedule frequency.
     *
     * Uses the schedule's timezone for calculations and returns a Carbon
     * instance in that timezone.
     */
    public function calculateNextRunAt(): Carbon
    {
        $timezone = $this->timezone ?? 'UTC';
        $now = Carbon::now($timezone);
        [$hour, $minute] = explode(':', $this->time_of_day);

        return match ($this->frequency) {
            ScheduleFrequency::Daily => $this->calculateNextDailyRun($now, (int) $hour, (int) $minute),
            ScheduleFrequency::Weekly => $this->calculateNextWeeklyRun($now, (int) $hour, (int) $minute),
            ScheduleFrequency::Monthly => $this->calculateNextMonthlyRun($now, (int) $hour, (int) $minute),
        };
    }

    /**
     * Calculate next daily run time.
     */
    protected function calculateNextDailyRun(Carbon $now, int $hour, int $minute): Carbon
    {
        $nextRun = $now->copy()->setTime($hour, $minute, 0);

        if ($nextRun->lte($now)) {
            $nextRun->addDay();
        }

        return $nextRun;
    }

    /**
     * Calculate next weekly run time.
     */
    protected function calculateNextWeeklyRun(Carbon $now, int $hour, int $minute): Carbon
    {
        $nextRun = $now->copy()->setTime($hour, $minute, 0);
        $targetDayOfWeek = $this->day_of_week;

        // Carbon uses 0=Sunday, 6=Saturday, matching our convention
        $currentDayOfWeek = $now->dayOfWeek;

        if ($currentDayOfWeek === $targetDayOfWeek && $nextRun->gt($now)) {
            // Today is the target day and time hasn't passed yet
            return $nextRun;
        }

        // Calculate days until next occurrence of target day
        $daysUntilTarget = ($targetDayOfWeek - $currentDayOfWeek + 7) % 7;

        // If today is the target day but time has passed, schedule for next week
        if ($daysUntilTarget === 0) {
            $daysUntilTarget = 7;
        }

        return $nextRun->addDays($daysUntilTarget);
    }

    /**
     * Calculate next monthly run time.
     */
    protected function calculateNextMonthlyRun(Carbon $now, int $hour, int $minute): Carbon
    {
        $nextRun = $now->copy()->setTime($hour, $minute, 0);
        $dayOfMonth = $this->day_of_month;

        if ($dayOfMonth === 'last') {
            // Schedule for last day of month
            $nextRun->endOfMonth()->setTime($hour, $minute, 0);

            if ($nextRun->lte($now)) {
                // Move to first day of next month, then to end of that month
                // This ensures correct month rollover (Jan 31 -> Feb 28, not March 3)
                $nextRun->startOfMonth()->addMonthNoOverflow()->endOfMonth()->setTime($hour, $minute, 0);
            }

            return $nextRun;
        }

        $targetDay = (int) $dayOfMonth;

        // Set to target day, handling months with fewer days
        $daysInMonth = $nextRun->daysInMonth;
        $actualDay = min($targetDay, $daysInMonth);
        $nextRun->setDay($actualDay);

        if ($nextRun->lte($now)) {
            // Move to next month
            $nextRun->addMonth();
            $daysInMonth = $nextRun->daysInMonth;
            $actualDay = min($targetDay, $daysInMonth);
            $nextRun->setDay($actualDay);
        }

        return $nextRun;
    }

    /**
     * Mark the schedule as run with the given status.
     *
     * Updates last run timestamp and status, and manages the failure count.
     * On success, resets the failure counter.
     * On failure, increments the failure counter.
     */
    public function markAsRun(bool $success, ?string $error = null): void
    {
        $this->last_run_at = Carbon::now();
        $this->last_run_status = $success ? 'success' : 'failed';

        if ($success) {
            $this->resetFailureCount();
        } else {
            $this->incrementFailureCount();
        }

        $this->save();
    }

    /**
     * Increment the consecutive failure count.
     */
    public function incrementFailureCount(): void
    {
        $this->consecutive_failures++;
        $this->save();
    }

    /**
     * Reset the consecutive failure count to zero.
     */
    public function resetFailureCount(): void
    {
        $this->consecutive_failures = 0;
        $this->save();
    }

    /**
     * Check if the schedule should be disabled due to too many failures.
     */
    public function shouldDisable(): bool
    {
        return $this->consecutive_failures >= self::MAX_CONSECUTIVE_FAILURES;
    }
}

<?php

namespace App\Http\Resources;

use App\Models\ReportSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming ReportSchedule model data.
 *
 * Provides consistent JSON representation of report schedules including
 * schedule configuration, status, timing, and nested distribution list.
 *
 * @mixin ReportSchedule
 */
class ReportScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'report_type' => $this->report_type->value,
            'report_type_label' => $this->report_type->label(),
            'report_configuration' => $this->report_configuration,
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'schedule_display' => $this->getScheduleDisplayString(),
            'day_of_week' => $this->day_of_week,
            'day_of_month' => $this->day_of_month,
            'time_of_day' => $this->time_of_day,
            'timezone' => $this->timezone,
            'format' => $this->format->value,
            'format_label' => $this->format->label(),
            'is_enabled' => $this->is_enabled,
            'consecutive_failures' => $this->consecutive_failures,
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'last_run_status' => $this->last_run_status,
            'distribution_list' => $this->whenLoaded('distributionList', fn () => [
                'id' => $this->distributionList->id,
                'name' => $this->distributionList->name,
                'members_count' => $this->distributionList->members()->count(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Generate a human-readable schedule display string.
     */
    protected function getScheduleDisplayString(): string
    {
        $time = $this->time_of_day;
        $timezone = $this->timezone;

        return match ($this->frequency->value) {
            'daily' => "Daily at {$time} {$timezone}",
            'weekly' => $this->getWeeklyDisplayString($time, $timezone),
            'monthly' => $this->getMonthlyDisplayString($time, $timezone),
            default => "At {$time} {$timezone}",
        };
    }

    /**
     * Generate display string for weekly schedules.
     */
    protected function getWeeklyDisplayString(string $time, string $timezone): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayName = $days[$this->day_of_week] ?? 'Unknown';

        return "Weekly on {$dayName} at {$time} {$timezone}";
    }

    /**
     * Generate display string for monthly schedules.
     */
    protected function getMonthlyDisplayString(string $time, string $timezone): string
    {
        if ($this->day_of_month === 'last') {
            return "Monthly on the last day at {$time} {$timezone}";
        }

        $day = (int) $this->day_of_month;
        $suffix = $this->getOrdinalSuffix($day);

        return "Monthly on the {$day}{$suffix} at {$time} {$timezone}";
    }

    /**
     * Get ordinal suffix for a day number.
     */
    protected function getOrdinalSuffix(int $number): string
    {
        if ($number >= 11 && $number <= 13) {
            return 'th';
        }

        return match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}

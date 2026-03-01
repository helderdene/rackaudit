<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Service for directly logging activities to the activity log.
 *
 * Use this service when you need programmatic control over activity logging,
 * rather than relying on the automatic Loggable trait.
 */
class ActivityLogService
{
    /**
     * Log an activity for a given subject model.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        Model $subject,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $causer = null
    ): ActivityLog {
        // Get excluded fields from the subject model if it has the method
        $excludedFields = $this->getExcludedFields($subject);

        // Filter out excluded fields from old and new values
        $filteredOldValues = $oldValues !== null ? $this->filterExcludedFields($oldValues, $excludedFields) : null;
        $filteredNewValues = $newValues !== null ? $this->filterExcludedFields($newValues, $excludedFields) : null;

        // Get request context
        $request = request();
        $ipAddress = $request->ip() ?? '0.0.0.0';
        $userAgent = $request->userAgent();

        // Use provided causer or fall back to authenticated user
        $causerId = $causer?->id ?? Auth::id();

        return ActivityLog::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'causer_id' => $causerId,
            'action' => $action,
            'old_values' => $filteredOldValues,
            'new_values' => $filteredNewValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Get the fields that should be excluded from activity logging.
     *
     * @return list<string>
     */
    protected function getExcludedFields(Model $subject): array
    {
        // Check if the model has the getExcludedFromActivityLog method (from Loggable trait)
        if (method_exists($subject, 'getExcludedFromActivityLog')) {
            return $subject->getExcludedFromActivityLog();
        }

        return [];
    }

    /**
     * Filter out excluded fields from the given values array.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $excludedFields
     * @return array<string, mixed>
     */
    protected function filterExcludedFields(array $values, array $excludedFields): array
    {
        return array_diff_key($values, array_flip($excludedFields));
    }
}

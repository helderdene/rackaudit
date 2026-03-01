<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Trait to automatically log model create, update, delete, and restore events.
 *
 * Apply this trait to any Eloquent model that should have its changes tracked
 * in the activity log system.
 *
 * Models can customize logging behavior with these optional properties:
 * - $excludeFromActivityLog: Fields to exclude from logging
 * - $logFullState: When true, logs complete state snapshots on updates (not just changes)
 *
 * Models can implement getEnrichedAttributesForLog() to add additional context
 * to logged values (e.g., resolved foreign key references).
 *
 * @property array<int, string> $excludeFromActivityLog Fields to exclude from logging
 * @property bool $logFullState Whether to log full state snapshots on updates
 */
trait Loggable
{
    /**
     * Boot the trait and register model event listeners.
     */
    public static function bootLoggable(): void
    {
        static::created(function (Model $model): void {
            $attributes = $model->getAttributes();

            // Add enriched attributes if the model provides them
            if (method_exists($model, 'getEnrichedAttributesForLog')) {
                $attributes = array_merge($attributes, $model->getEnrichedAttributesForLog());
            }

            self::logActivity($model, 'created', null, $attributes);
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();

            // Only log if there are actual changes
            if (empty($changes)) {
                return;
            }

            // Check if model wants full state logging
            if (self::shouldLogFullState($model)) {
                $oldValues = $model->getOriginal();
                $newValues = $model->getAttributes();

                // Add enriched attributes if the model provides them
                if (method_exists($model, 'getEnrichedAttributesForLog')) {
                    $enriched = $model->getEnrichedAttributesForLog();
                    $oldValues = array_merge($oldValues, $enriched);
                    $newValues = array_merge($newValues, $enriched);
                }

                self::logActivity($model, 'updated', $oldValues, $newValues);
            } else {
                // Default behavior: only log changed fields
                $oldValuesFiltered = array_intersect_key($model->getOriginal(), $changes);
                self::logActivity($model, 'updated', $oldValuesFiltered, $changes);
            }
        });

        static::deleted(function (Model $model): void {
            $attributes = $model->getOriginal();

            // Add enriched attributes if the model provides them
            if (method_exists($model, 'getEnrichedAttributesForLog')) {
                $attributes = array_merge($attributes, $model->getEnrichedAttributesForLog());
            }

            self::logActivity($model, 'deleted', $attributes, null);
        });

        // Register restored event listener for models using SoftDeletes
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class))) {
            static::restored(function (Model $model): void {
                $attributes = $model->getAttributes();

                // Add enriched attributes if the model provides them
                if (method_exists($model, 'getEnrichedAttributesForLog')) {
                    $attributes = array_merge($attributes, $model->getEnrichedAttributesForLog());
                }

                self::logActivity($model, 'restored', null, $attributes);
            });
        }
    }

    /**
     * Determine if the model should log full state on updates.
     */
    protected static function shouldLogFullState(Model $model): bool
    {
        return property_exists($model, 'logFullState') && $model->logFullState === true;
    }

    /**
     * Get the fields that should be excluded from activity logging.
     *
     * @return list<string>
     */
    public function getExcludedFromActivityLog(): array
    {
        if (property_exists($this, 'excludeFromActivityLog') && is_array($this->excludeFromActivityLog)) {
            return $this->excludeFromActivityLog;
        }

        return [];
    }

    /**
     * Create an activity log entry for the given model event.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    protected static function logActivity(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        $excludedFields = self::getExcludedFields($model);

        // Filter out excluded fields from old and new values
        $filteredOldValues = $oldValues ? self::filterExcludedFields($oldValues, $excludedFields) : null;
        $filteredNewValues = $newValues ? self::filterExcludedFields($newValues, $excludedFields) : null;

        // Get the current request context
        $request = request();

        // Use Auth facade for better test compatibility
        $causer = Auth::user();
        $ipAddress = $request->ip() ?? '0.0.0.0';
        $userAgent = $request->userAgent();

        ActivityLog::create([
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'causer_id' => $causer?->id,
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
    protected static function getExcludedFields(Model $model): array
    {
        // Check if the model has the getExcludedFromActivityLog method (from this trait)
        if (method_exists($model, 'getExcludedFromActivityLog')) {
            return $model->getExcludedFromActivityLog();
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
    protected static function filterExcludedFields(array $values, array $excludedFields): array
    {
        return array_diff_key($values, array_flip($excludedFields));
    }
}

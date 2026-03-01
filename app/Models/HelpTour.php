<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HelpTour model representing a guided feature tour.
 *
 * Tours guide users through complex workflows by highlighting UI elements
 * and displaying step-by-step instructions. Each tour contains multiple
 * steps that are displayed in sequence.
 */
class HelpTour extends Model
{
    /** @use HasFactory<\Database\Factories\HelpTourFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'context_key',
        'description',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the steps for this tour, ordered by step_order.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(HelpTourStep::class)->orderBy('step_order');
    }

    /**
     * Get the user interactions for this tour.
     */
    public function userInteractions(): HasMany
    {
        return $this->hasMany(UserHelpInteraction::class);
    }

    /**
     * Scope to filter active tours only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter tours by context key.
     */
    public function scopeByContextKey(Builder $query, string $contextKey): Builder
    {
        return $query->where('context_key', $contextKey);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

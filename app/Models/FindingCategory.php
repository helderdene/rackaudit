<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FindingCategory model for categorizing findings.
 *
 * Categories help organize findings by type. Default categories are seeded
 * from DiscrepancyType values, and users can create custom categories.
 */
class FindingCategory extends Model
{
    /** @use HasFactory<\Database\Factories\FindingCategoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get all findings in this category.
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'finding_category_id');
    }

    /**
     * Scope to get default categories.
     */
    public function scopeDefaults($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get custom (non-default) categories.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_default', false);
    }

    /**
     * Scope to order categories with defaults first, then alphabetically.
     */
    public function scopeOrderedByDefault($query)
    {
        return $query->orderByDesc('is_default')->orderBy('name');
    }
}

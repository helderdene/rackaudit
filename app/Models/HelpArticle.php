<?php

namespace App\Models;

use App\Enums\HelpArticleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HelpArticle model representing help documentation content.
 *
 * Stores contextual help content including tooltips, tour steps, and full articles.
 * Content supports Markdown formatting and can be scoped by context_key to
 * display relevant help based on the current page or feature.
 */
class HelpArticle extends Model
{
    /** @use HasFactory<\Database\Factories\HelpArticleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'title',
        'content',
        'context_key',
        'article_type',
        'category',
        'sort_order',
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
            'article_type' => HelpArticleType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the tour steps that use this article.
     */
    public function tourSteps(): HasMany
    {
        return $this->hasMany(HelpTourStep::class);
    }

    /**
     * Get the user interactions for this article.
     */
    public function userInteractions(): HasMany
    {
        return $this->hasMany(UserHelpInteraction::class);
    }

    /**
     * Scope to filter active articles only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter articles by context key.
     */
    public function scopeByContextKey(Builder $query, string $contextKey): Builder
    {
        return $query->where('context_key', $contextKey);
    }

    /**
     * Scope to filter articles by type.
     */
    public function scopeByType(Builder $query, HelpArticleType $type): Builder
    {
        return $query->where('article_type', $type);
    }

    /**
     * Scope to filter articles by category.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

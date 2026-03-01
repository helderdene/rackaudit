<?php

namespace App\Models;

use App\Enums\HelpTourStepPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HelpTourStep model representing a single step in a feature tour.
 *
 * Each step references a help article for content and specifies the
 * target UI element to highlight along with popover positioning.
 */
class HelpTourStep extends Model
{
    /** @use HasFactory<\Database\Factories\HelpTourStepFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'help_tour_id',
        'help_article_id',
        'target_selector',
        'position',
        'step_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => HelpTourStepPosition::class,
            'step_order' => 'integer',
        ];
    }

    /**
     * Get the tour that this step belongs to.
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(HelpTour::class, 'help_tour_id');
    }

    /**
     * Get the article that provides content for this step.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'help_article_id');
    }
}

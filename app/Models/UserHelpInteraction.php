<?php

namespace App\Models;

use App\Enums\HelpInteractionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserHelpInteraction model tracking user interactions with help content.
 *
 * Records when users view articles, dismiss tooltips, or complete tours.
 * This data is used to personalize help content display and track
 * feature adoption through tour completion metrics.
 */
class UserHelpInteraction extends Model
{
    /** @use HasFactory<\Database\Factories\UserHelpInteractionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'help_article_id',
        'help_tour_id',
        'interaction_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interaction_type' => HelpInteractionType::class,
        ];
    }

    /**
     * Get the user who made this interaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the help article associated with this interaction.
     */
    public function helpArticle(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class);
    }

    /**
     * Get the help tour associated with this interaction.
     */
    public function helpTour(): BelongsTo
    {
        return $this->belongsTo(HelpTour::class);
    }
}

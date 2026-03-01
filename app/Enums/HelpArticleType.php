<?php

namespace App\Enums;

/**
 * Type enum for help articles.
 *
 * Classifies help articles by their intended usage:
 * - Tooltip: Short contextual help shown in popovers
 * - TourStep: Content displayed as part of a feature tour
 * - Article: Full-length help documentation articles
 */
enum HelpArticleType: string
{
    case Tooltip = 'tooltip';
    case TourStep = 'tour_step';
    case Article = 'article';

    /**
     * Get the human-readable label for the article type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Tooltip => 'Tooltip',
            self::TourStep => 'Tour Step',
            self::Article => 'Article',
        };
    }
}

<?php

namespace App\Enums;

/**
 * Type enum for user help interactions.
 *
 * Tracks how users interact with help content:
 * - Viewed: User has viewed a help article
 * - Dismissed: User has dismissed a tooltip with "Don't show again"
 * - CompletedTour: User has completed a feature tour
 */
enum HelpInteractionType: string
{
    case Viewed = 'viewed';
    case Dismissed = 'dismissed';
    case CompletedTour = 'completed_tour';

    /**
     * Get the human-readable label for the interaction type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Viewed => 'Viewed',
            self::Dismissed => 'Dismissed',
            self::CompletedTour => 'Completed Tour',
        };
    }
}

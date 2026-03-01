<?php

namespace App\Enums;

/**
 * Position enum for help tour step popovers.
 *
 * Determines where the tour step popover appears relative to the target element:
 * - Top: Above the target element
 * - Right: To the right of the target element
 * - Bottom: Below the target element
 * - Left: To the left of the target element
 */
enum HelpTourStepPosition: string
{
    case Top = 'top';
    case Right = 'right';
    case Bottom = 'bottom';
    case Left = 'left';

    /**
     * Get the human-readable label for the position.
     */
    public function label(): string
    {
        return match ($this) {
            self::Top => 'Top',
            self::Right => 'Right',
            self::Bottom => 'Bottom',
            self::Left => 'Left',
        };
    }
}

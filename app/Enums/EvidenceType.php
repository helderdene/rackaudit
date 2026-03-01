<?php

namespace App\Enums;

/**
 * Evidence type for finding evidence records.
 *
 * Indicates what type of evidence is attached to a finding:
 * - File: An uploaded file (image, PDF, document)
 * - Text: A text note or description
 */
enum EvidenceType: string
{
    case File = 'file';
    case Text = 'text';

    /**
     * Get the human-readable label for the evidence type.
     */
    public function label(): string
    {
        return match ($this) {
            self::File => 'File',
            self::Text => 'Text Note',
        };
    }
}

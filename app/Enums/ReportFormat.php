<?php

namespace App\Enums;

/**
 * Report format for scheduled report attachments.
 *
 * Defines the available file formats for generated reports:
 * - PDF: Portable Document Format for formatted, printable reports
 * - CSV: Comma-Separated Values for data analysis and spreadsheet import
 */
enum ReportFormat: string
{
    case PDF = 'pdf';
    case CSV = 'csv';

    /**
     * Get the human-readable label for the format.
     */
    public function label(): string
    {
        return match ($this) {
            self::PDF => 'PDF',
            self::CSV => 'CSV',
        };
    }

    /**
     * Get the MIME type for the format.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::PDF => 'application/pdf',
            self::CSV => 'text/csv',
        };
    }

    /**
     * Get the file extension for the format.
     */
    public function extension(): string
    {
        return match ($this) {
            self::PDF => 'pdf',
            self::CSV => 'csv',
        };
    }
}

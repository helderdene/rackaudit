<?php

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Abstract base class for template exports.
 *
 * Provides common functionality for generating XLSX import templates
 * with enum dropdowns, header styling, and example data.
 */
abstract class AbstractTemplateExport implements FromArray, WithHeadings, WithStyles, WithEvents, ShouldAutoSize, WithTitle
{
    /**
     * Header row background color.
     */
    protected const HEADER_BG_COLOR = '4472C4';

    /**
     * Header text color.
     */
    protected const HEADER_TEXT_COLOR = 'FFFFFF';

    /**
     * Required field header background color.
     */
    protected const REQUIRED_BG_COLOR = 'FFE699';

    /**
     * Number of data rows to apply validation to.
     */
    protected const VALIDATION_ROW_COUNT = 1000;

    /**
     * Get the column headers for this template.
     *
     * @return array<string>
     */
    abstract public function headings(): array;

    /**
     * Get the example data row(s) for this template.
     *
     * @return array<int, array<mixed>>
     */
    abstract public function array(): array;

    /**
     * Get the sheet title for this template.
     */
    abstract public function title(): string;

    /**
     * Get columns that should have dropdown validation.
     *
     * Returns an array where keys are column headers and values are enum class names.
     *
     * @return array<string, class-string>
     */
    protected function enumColumns(): array
    {
        return [];
    }

    /**
     * Get columns that are required (for visual indication).
     *
     * @return array<string>
     */
    protected function requiredColumns(): array
    {
        return [];
    }

    /**
     * Get column comments/descriptions for helper text.
     *
     * @return array<string, string>
     */
    protected function columnComments(): array
    {
        return [];
    }

    /**
     * Apply styles to the worksheet.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));

        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => self::HEADER_TEXT_COLOR],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::HEADER_BG_COLOR],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Register events for the export.
     *
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->applyDropdowns($sheet);
                $this->applyComments($sheet);
                $this->highlightRequiredColumns($sheet);
            },
        ];
    }

    /**
     * Apply dropdown validation to enum columns.
     */
    protected function applyDropdowns(Worksheet $sheet): void
    {
        $headings = $this->headings();
        $enumColumns = $this->enumColumns();

        foreach ($enumColumns as $columnName => $enumClass) {
            $columnIndex = array_search($columnName, $headings);
            if ($columnIndex === false) {
                continue;
            }

            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex + 1);
            $dropdownValues = $this->getEnumLabels($enumClass);

            // Apply validation from row 2 to VALIDATION_ROW_COUNT
            $range = $columnLetter . '2:' . $columnLetter . self::VALIDATION_ROW_COUNT;
            $this->setDropdownValidation($sheet, $range, $dropdownValues);
        }
    }

    /**
     * Apply cell comments with helper text.
     */
    protected function applyComments(Worksheet $sheet): void
    {
        $headings = $this->headings();
        $comments = $this->columnComments();

        foreach ($comments as $columnName => $comment) {
            $columnIndex = array_search($columnName, $headings);
            if ($columnIndex === false) {
                continue;
            }

            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex + 1);
            $cell = $sheet->getCell($columnLetter . '1');

            // Add comment to header cell
            $cellComment = $sheet->getComment($columnLetter . '1');
            $cellComment->getText()->createTextRun($comment);
            $cellComment->setWidth('300pt');
            $cellComment->setHeight('100pt');
        }
    }

    /**
     * Highlight required column headers with different background.
     */
    protected function highlightRequiredColumns(Worksheet $sheet): void
    {
        $headings = $this->headings();
        $requiredColumns = $this->requiredColumns();

        foreach ($requiredColumns as $columnName) {
            $columnIndex = array_search($columnName, $headings);
            if ($columnIndex === false) {
                continue;
            }

            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex + 1);

            // Highlight required columns with yellow background (don't modify header text)
            $sheet->getStyle($columnLetter . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB(self::REQUIRED_BG_COLOR);

            // Set dark text color for better contrast on yellow background
            $sheet->getStyle($columnLetter . '1')->getFont()
                ->getColor()->setRGB('000000');
        }
    }

    /**
     * Get human-readable labels from an enum class.
     *
     * @param class-string $enumClass
     * @return array<string>
     */
    protected function getEnumLabels(string $enumClass): array
    {
        $labels = [];

        foreach ($enumClass::cases() as $case) {
            if (method_exists($case, 'label')) {
                $labels[] = $case->label();
            } else {
                $labels[] = $case->value ?? $case->name;
            }
        }

        return $labels;
    }

    /**
     * Set dropdown validation for a cell range.
     *
     * @param array<string> $values
     */
    protected function setDropdownValidation(Worksheet $sheet, string $range, array $values): void
    {
        $validation = $sheet->getCell(explode(':', $range)[0])->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"' . implode(',', $values) . '"');

        // Apply the same validation to the entire range
        $sheet->setDataValidation($range, $validation);
    }

    /**
     * Get a formatted dropdown formula from enum values.
     *
     * @param class-string $enumClass
     */
    protected function getDropdownFormula(string $enumClass): string
    {
        $labels = $this->getEnumLabels($enumClass);

        return '"' . implode(',', $labels) . '"';
    }
}

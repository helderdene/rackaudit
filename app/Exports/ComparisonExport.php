<?php

namespace App\Exports;

use App\DTOs\ComparisonResult;
use App\DTOs\ComparisonResultCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export class for connection comparison results.
 *
 * Exports comparison data as CSV with columns for source device/port,
 * destination device/port, cable types, discrepancy type, acknowledgment
 * status, and notes. Works with ComparisonResultCollection DTOs.
 */
class ComparisonExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
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
     * The comparison results to export.
     */
    protected ComparisonResultCollection $results;

    /**
     * Create a new export instance.
     */
    public function __construct(ComparisonResultCollection $results)
    {
        $this->results = $results;
    }

    /**
     * Get the column headers for the export.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'Source Device',
            'Source Port',
            'Dest Device',
            'Dest Port',
            'Expected Cable Type',
            'Actual Cable Type',
            'Discrepancy Type',
            'Acknowledged',
            'Notes',
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Connection Comparison';
    }

    /**
     * Get the collection of data to export.
     *
     * @return Collection<int, array<mixed>>
     */
    public function collection(): Collection
    {
        return collect($this->results->all())
            ->map(fn (ComparisonResult $result) => $this->transformRow($result));
    }

    /**
     * Transform a ComparisonResult DTO to an array row matching the headings.
     *
     * @return array<mixed>
     */
    protected function transformRow(ComparisonResult $result): array
    {
        $sourceDevice = $result->getSourceDevice();
        $destDevice = $result->getDestDevice();

        // Get cable types from expected and actual connections
        $expectedCableType = $result->expectedConnection?->cable_type?->label() ?? '';
        $actualCableType = $result->actualConnection?->cable_type?->label() ?? '';

        // Get acknowledgment details
        $isAcknowledged = $result->isAcknowledged() ? 'Yes' : 'No';
        $notes = $result->acknowledgment?->notes ?? '';

        return [
            $sourceDevice?->name ?? '',
            $result->sourcePort?->label ?? '',
            $destDevice?->name ?? '',
            $result->destPort?->label ?? '',
            $expectedCableType,
            $actualCableType,
            $result->discrepancyType->label(),
            $isAcknowledged,
            $notes,
        ];
    }

    /**
     * Apply styles to the worksheet.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
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
     * Get the comparison results.
     */
    public function getResults(): ComparisonResultCollection
    {
        return $this->results;
    }
}

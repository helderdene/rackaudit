<?php

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Instructions sheet for connection template export.
 *
 * Provides documentation about the expected format,
 * column descriptions, and sample data examples.
 */
class ConnectionInstructionsSheet implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
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
     * Section header background color.
     */
    protected const SECTION_BG_COLOR = 'D9E2F3';

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Instructions';
    }

    /**
     * Get the instructions content.
     *
     * @return array<int, array<mixed>>
     */
    public function array(): array
    {
        return [
            ['Connection Template Instructions'],
            [''],
            ['This template is used to import expected connections from implementation specifications.'],
            ['Fill in the "Connections" sheet with your connection data following the format below.'],
            [''],
            ['COLUMN DESCRIPTIONS', ''],
            ['Column', 'Description', 'Required', 'Example'],
            ['Source Device', 'Name of the source device as it appears in the system', 'Yes', 'Server-001'],
            ['Source Port', 'Port label on the source device', 'Yes', 'eth0'],
            ['Dest Device', 'Name of the destination device as it appears in the system', 'Yes', 'Switch-001'],
            ['Dest Port', 'Port label on the destination device', 'Yes', 'port-1'],
            ['Cable Type', 'Type of cable used for the connection (optional)', 'No', 'Cat6'],
            ['Cable Length', 'Length of the cable in meters (optional)', 'No', '3.5'],
            [''],
            ['CABLE TYPE OPTIONS', ''],
            ['Type', 'Description'],
            ['Cat5e', 'Category 5e Ethernet cable'],
            ['Cat6', 'Category 6 Ethernet cable'],
            ['Cat6a', 'Category 6a Ethernet cable'],
            ['Fiber SM', 'Single-mode fiber optic cable'],
            ['Fiber MM', 'Multi-mode fiber optic cable'],
            ['C13', 'IEC C13 power cable'],
            ['C14', 'IEC C14 power cable'],
            ['C19', 'IEC C19 power cable'],
            ['C20', 'IEC C20 power cable'],
            [''],
            ['SAMPLE DATA', ''],
            ['Source Device', 'Source Port', 'Dest Device', 'Dest Port', 'Cable Type', 'Cable Length'],
            ['Server-001', 'eth0', 'Switch-001', 'port-1', 'Cat6', '3.5'],
            ['Server-001', 'eth1', 'Switch-001', 'port-2', 'Cat6a', '5.0'],
            ['Server-002', 'fiber-0', 'CoreSwitch-001', 'sfp-1', 'Fiber SM', '10.0'],
            ['PDU-001', 'outlet-1', 'Server-001', 'psu-1', 'C13', '2.0'],
            [''],
            ['NOTES', ''],
            ['- Device and port names must match exactly as they appear in the system for automatic matching.'],
            ['- If names do not match exactly, the system will suggest similar matches for review.'],
            ['- You can review and correct any unrecognized devices or ports after import.'],
            ['- All imported connections will be in "Pending Review" status until confirmed.'],
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
            // Title row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 16,
                ],
            ],
            // Section header styling
            6 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
            ],
            15 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
            ],
            27 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
            ],
            33 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
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

                // Style the column description header row
                $sheet->getStyle('A7:D7')->applyFromArray([
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
                ]);

                // Style the cable type options header row
                $sheet->getStyle('A16:B16')->applyFromArray([
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
                ]);

                // Style the sample data header row
                $sheet->getStyle('A28:F28')->applyFromArray([
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
                ]);

                // Apply section header background
                $sheet->getStyle('A6:B6')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::SECTION_BG_COLOR);

                $sheet->getStyle('A15:B15')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::SECTION_BG_COLOR);

                $sheet->getStyle('A27:B27')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::SECTION_BG_COLOR);

                $sheet->getStyle('A33:B33')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::SECTION_BG_COLOR);

                // Set column widths
                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(55);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(20);
                $sheet->getColumnDimension('E')->setWidth(15);
                $sheet->getColumnDimension('F')->setWidth(15);
            },
        ];
    }
}

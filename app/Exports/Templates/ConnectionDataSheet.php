<?php

namespace App\Exports\Templates;

use App\Enums\CableType;

/**
 * Data sheet for connection template export.
 *
 * Provides the main data entry sheet with columns for expected connections.
 * Includes dropdown validation for cable type and visual styling.
 */
class ConnectionDataSheet extends AbstractTemplateExport
{
    /**
     * Get the column headers for the connection template.
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
            'Cable Type',
            'Cable Length',
        ];
    }

    /**
     * Get example data for the connection template.
     *
     * @return array<int, array<mixed>>
     */
    public function array(): array
    {
        return [
            [
                'Server-001',
                'eth0',
                'Switch-001',
                'port-1',
                CableType::Cat6->label(),
                '3.5',
            ],
            [
                'Server-002',
                'eth1',
                'Switch-001',
                'port-2',
                CableType::Cat6a->label(),
                '5.0',
            ],
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Connections';
    }

    /**
     * Get columns that should have dropdown validation.
     *
     * @return array<string, class-string>
     */
    protected function enumColumns(): array
    {
        return [
            'Cable Type' => CableType::class,
        ];
    }

    /**
     * Get required columns for visual indication.
     *
     * @return array<string>
     */
    protected function requiredColumns(): array
    {
        return [
            'Source Device',
            'Source Port',
            'Dest Device',
            'Dest Port',
        ];
    }

    /**
     * Get column comments/descriptions for helper text.
     *
     * @return array<string, string>
     */
    protected function columnComments(): array
    {
        return [
            'Source Device' => 'Required. Name of the source device (must exist in system).',
            'Source Port' => 'Required. Port label on the source device (must exist on device).',
            'Dest Device' => 'Required. Name of the destination device (must exist in system).',
            'Dest Port' => 'Required. Port label on the destination device (must exist on device).',
            'Cable Type' => 'Optional. Type of cable: Cat5e, Cat6, Cat6a, Fiber SM, Fiber MM, C13, C14, C19, C20.',
            'Cable Length' => 'Optional. Cable length in meters (e.g., 3.5).',
        ];
    }
}

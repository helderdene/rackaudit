<?php

namespace App\Exports\Templates;

use App\Enums\RackStatus;
use App\Enums\RackUHeight;

/**
 * Template export for Rack entities.
 *
 * Generates an XLSX template with headers, example data,
 * enum dropdowns for u_height and status.
 */
class RackTemplateExport extends AbstractTemplateExport
{
    /**
     * Get the column headers for the rack template.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'datacenter_name',
            'room_name',
            'row_name',
            'name',
            'position',
            'u_height',
            'serial_number',
            'status',
        ];
    }

    /**
     * Get example data for the rack template.
     *
     * @return array<int, array<mixed>>
     */
    public function array(): array
    {
        return [
            [
                'Example Datacenter',
                'Server Room A',
                'Row 1',
                'Rack A1',
                1,
                RackUHeight::U42->label(),
                'SN-RACK-001',
                RackStatus::Active->label(),
            ],
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Racks';
    }

    /**
     * Get columns that should have dropdown validation.
     *
     * @return array<string, class-string>
     */
    protected function enumColumns(): array
    {
        return [
            'u_height' => RackUHeight::class,
            'status' => RackStatus::class,
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
            'datacenter_name',
            'room_name',
            'row_name',
            'name',
            'position',
            'u_height',
            'status',
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
            'datacenter_name' => 'Required. Name of the parent datacenter (must exist).',
            'room_name' => 'Required. Name of the parent room (must exist).',
            'row_name' => 'Required. Name of the parent row (must exist).',
            'name' => 'Required. Unique name for the rack within the row.',
            'position' => 'Required. Numeric position of the rack in the row (1, 2, 3, ...).',
            'u_height' => 'Required. Rack height in units: 42U, 45U, or 48U.',
            'serial_number' => 'Optional. Manufacturer serial number for the rack.',
            'status' => 'Required. Active, Inactive, or Maintenance status.',
        ];
    }
}

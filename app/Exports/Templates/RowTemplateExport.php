<?php

namespace App\Exports\Templates;

use App\Enums\RowOrientation;
use App\Enums\RowStatus;

/**
 * Template export for Row entities.
 *
 * Generates an XLSX template with headers, example data,
 * enum dropdowns for orientation and status.
 */
class RowTemplateExport extends AbstractTemplateExport
{
    /**
     * Get the column headers for the row template.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'datacenter_name',
            'room_name',
            'name',
            'position',
            'orientation',
            'status',
        ];
    }

    /**
     * Get example data for the row template.
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
                1,
                RowOrientation::ColdAisle->label(),
                RowStatus::Active->label(),
            ],
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Rows';
    }

    /**
     * Get columns that should have dropdown validation.
     *
     * @return array<string, class-string>
     */
    protected function enumColumns(): array
    {
        return [
            'orientation' => RowOrientation::class,
            'status' => RowStatus::class,
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
            'name',
            'position',
            'orientation',
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
            'room_name' => 'Required. Name of the parent room within the datacenter (must exist).',
            'name' => 'Required. Unique name for the row within the room.',
            'position' => 'Required. Numeric position of the row in the room (1, 2, 3, ...).',
            'orientation' => 'Required. Hot Aisle or Cold Aisle designation for cooling.',
            'status' => 'Required. Active or Inactive status.',
        ];
    }
}

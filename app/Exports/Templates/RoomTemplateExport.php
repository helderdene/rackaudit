<?php

namespace App\Exports\Templates;

use App\Enums\RoomType;

/**
 * Template export for Room entities.
 *
 * Generates an XLSX template with headers, example data,
 * enum dropdowns for type, and field descriptions.
 */
class RoomTemplateExport extends AbstractTemplateExport
{
    /**
     * Get the column headers for the room template.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'datacenter_name',
            'name',
            'type',
            'description',
            'square_footage',
        ];
    }

    /**
     * Get example data for the room template.
     *
     * @return array<int, array<mixed>>
     */
    public function array(): array
    {
        return [
            [
                'Example Datacenter',
                'Server Room A',
                RoomType::ServerRoom->label(),
                'Primary server room with hot/cold aisle containment',
                5000,
            ],
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Rooms';
    }

    /**
     * Get columns that should have dropdown validation.
     *
     * @return array<string, class-string>
     */
    protected function enumColumns(): array
    {
        return [
            'type' => RoomType::class,
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
            'name',
            'type',
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
            'name' => 'Required. Unique name for the room within the datacenter.',
            'type' => 'Required. Type of room: Server Room, Network Closet, Cage/Colocation Space, Storage, or Electrical Room.',
            'description' => 'Optional. Description of the room and its purpose.',
            'square_footage' => 'Optional. Total floor space in square feet.',
        ];
    }
}

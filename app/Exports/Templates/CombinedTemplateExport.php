<?php

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Combined template export for all entity types.
 *
 * Generates an XLSX template with separate sheets for each
 * entity type: Datacenters, Rooms, Rows, Racks, Devices, and Ports.
 */
class CombinedTemplateExport implements WithMultipleSheets
{
    /**
     * Get the sheets for the combined template.
     *
     * @return array<AbstractTemplateExport>
     */
    public function sheets(): array
    {
        return [
            new DatacenterTemplateExport,
            new RoomTemplateExport,
            new RowTemplateExport,
            new RackTemplateExport,
            new DeviceTemplateExport,
            new PortTemplateExport,
        ];
    }
}

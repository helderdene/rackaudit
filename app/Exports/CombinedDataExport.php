<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Combined data export for all entity types.
 *
 * Generates an XLSX file with separate sheets for each entity type:
 * Datacenters, Rooms, Rows, Racks, Devices, and Ports.
 * Applies consistent hierarchical filters across all sheets.
 */
class CombinedDataExport implements WithMultipleSheets
{
    /**
     * Filters to apply consistently across all sheets.
     *
     * @var array<string, mixed>
     */
    protected array $filters = [];

    /**
     * Create a new combined export instance.
     *
     * @param  array<string, mixed>  $filters
     */
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Get the sheets for the combined export.
     *
     * Each sheet receives the same filters for consistent
     * hierarchical filtering across all entity types.
     *
     * @return array<AbstractDataExport>
     */
    public function sheets(): array
    {
        return [
            new DatacenterExport($this->filters),
            new RoomExport($this->filters),
            new RowExport($this->filters),
            new RackExport($this->filters),
            new DeviceExport($this->filters),
            new PortExport($this->filters),
        ];
    }

    /**
     * Get the filters applied to this export.
     *
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}

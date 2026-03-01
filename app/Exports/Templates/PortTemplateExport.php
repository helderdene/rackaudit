<?php

namespace App\Exports\Templates;

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;

/**
 * Template export for Port entities.
 *
 * Generates an XLSX template with headers, example data,
 * enum dropdowns for type, subtype, status, and direction.
 */
class PortTemplateExport extends AbstractTemplateExport
{
    /**
     * Get the column headers for the port template.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'device_name',
            'label',
            'type',
            'subtype',
            'status',
            'direction',
            'position_slot',
            'position_row',
            'position_column',
        ];
    }

    /**
     * Get example data for the port template.
     *
     * @return array<int, array<mixed>>
     */
    public function array(): array
    {
        return [
            [
                'Web Server 01',
                'eth0',
                PortType::Ethernet->label(),
                PortSubtype::Gbe10->label(),
                PortStatus::Available->label(),
                PortDirection::Bidirectional->label(),
                1,
                1,
                1,
            ],
            [
                'Web Server 01',
                'psu1',
                PortType::Power->label(),
                PortSubtype::C14->label(),
                PortStatus::Connected->label(),
                PortDirection::Input->label(),
                1,
                1,
                2,
            ],
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Ports';
    }

    /**
     * Get columns that should have dropdown validation.
     *
     * @return array<string, class-string>
     */
    protected function enumColumns(): array
    {
        return [
            'type' => PortType::class,
            'subtype' => PortSubtype::class,
            'status' => PortStatus::class,
            'direction' => PortDirection::class,
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
            'device_name',
            'label',
            'type',
            'subtype',
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
            'device_name' => 'Required. Name of the parent device (must exist in system).',
            'label' => 'Required. Port label/identifier (e.g., eth0, psu1, port-1).',
            'type' => 'Required. Port type: Ethernet, Fiber, or Power.',
            'subtype' => "Required. Port subtype. Must be compatible with type:\n- Ethernet: 1GbE, 10GbE, 25GbE, 40GbE, 100GbE\n- Fiber: LC, SC, MPO\n- Power: C13, C14, C19, C20",
            'status' => 'Optional. Port status: Available, Connected, Reserved, or Disabled. Defaults to Available.',
            'direction' => "Optional. Data/power flow direction:\n- Network (Ethernet/Fiber): Uplink, Downlink, or Bidirectional\n- Power: Input or Output\nDefaults based on type if not specified.",
            'position_slot' => 'Optional. Slot position for ports on modular devices.',
            'position_row' => 'Optional. Row position within a port group/slot.',
            'position_column' => 'Optional. Column position within a port group/slot.',
        ];
    }
}

<?php

namespace App\Exports\Templates;

use App\Enums\DeviceDepth;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;

/**
 * Template export for Device entities.
 *
 * Generates an XLSX template with headers, example data,
 * enum dropdowns for lifecycle_status, depth, width_type, and rack_face.
 */
class DeviceTemplateExport extends AbstractTemplateExport
{
    /**
     * Get the column headers for the device template.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'datacenter_name',
            'room_name',
            'row_name',
            'rack_name',
            'name',
            'device_type_name',
            'lifecycle_status',
            'serial_number',
            'manufacturer',
            'model',
            'purchase_date',
            'warranty_start_date',
            'warranty_end_date',
            'u_height',
            'depth',
            'width_type',
            'rack_face',
            'start_u',
            'notes',
        ];
    }

    /**
     * Get example data for the device template.
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
                'Web Server 01',
                'Dell PowerEdge R640',
                DeviceLifecycleStatus::Deployed->label(),
                'SN-SVR-001',
                'Dell',
                'PowerEdge R640',
                '2024-01-15',
                '2024-01-15',
                '2027-01-15',
                2,
                DeviceDepth::Standard->label(),
                DeviceWidthType::Full->label(),
                DeviceRackFace::Front->label(),
                1,
                'Primary web server for production environment',
            ],
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Devices';
    }

    /**
     * Get columns that should have dropdown validation.
     *
     * @return array<string, class-string>
     */
    protected function enumColumns(): array
    {
        return [
            'lifecycle_status' => DeviceLifecycleStatus::class,
            'depth' => DeviceDepth::class,
            'width_type' => DeviceWidthType::class,
            'rack_face' => DeviceRackFace::class,
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
            'name',
            'device_type_name',
            'lifecycle_status',
            'u_height',
            'depth',
            'width_type',
            'rack_face',
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
            'datacenter_name' => 'Optional. Name of the datacenter for placed devices. Leave empty for unplaced inventory.',
            'room_name' => 'Optional. Name of the room (required if placing in a rack).',
            'row_name' => 'Optional. Name of the row (required if placing in a rack).',
            'rack_name' => 'Optional. Name of the rack to place the device in.',
            'name' => 'Required. Unique name for the device.',
            'device_type_name' => 'Required. Must match an existing Device Type name in the system.',
            'lifecycle_status' => 'Required. Current lifecycle status: Ordered, Received, In Stock, Deployed, Maintenance, Decommissioned, or Disposed.',
            'serial_number' => 'Optional. Manufacturer serial number.',
            'manufacturer' => 'Optional. Device manufacturer name.',
            'model' => 'Optional. Device model number/name.',
            'purchase_date' => 'Optional. Date of purchase (YYYY-MM-DD format).',
            'warranty_start_date' => 'Optional. Warranty start date (YYYY-MM-DD format).',
            'warranty_end_date' => 'Optional. Warranty end date (YYYY-MM-DD format).',
            'u_height' => 'Required. Height of the device in rack units (1, 2, 4, etc.).',
            'depth' => 'Required. Physical depth: Standard, Deep, or Shallow.',
            'width_type' => 'Required. Width type: Full Width, Half Left, or Half Right.',
            'rack_face' => 'Required. Mounting side: Front or Rear.',
            'start_u' => 'Optional. Starting rack unit position (required if placing in a rack). Bottom unit number where device starts.',
            'notes' => 'Optional. Additional notes about the device.',
        ];
    }
}

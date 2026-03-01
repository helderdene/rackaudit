<?php

namespace App\Imports;

use App\Enums\DeviceDepth;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;

/**
 * Import class for Device entities.
 *
 * Handles validation and creation of devices from spreadsheet data.
 * Devices require parent datacenter > room > row > rack reference by name (optional for unplaced).
 * DeviceType is referenced by name and looked up in device_types table.
 */
class DeviceImport extends AbstractEntityImport
{
    /**
     * Cached datacenter for the current row.
     */
    protected ?Datacenter $cachedDatacenter = null;

    /**
     * Cached room for the current row.
     */
    protected ?Room $cachedRoom = null;

    /**
     * Cached row for the current device.
     */
    protected ?Row $cachedRow = null;

    /**
     * Cached rack for the current device.
     */
    protected ?Rack $cachedRack = null;

    /**
     * Cached device type for the current device.
     */
    protected ?DeviceType $cachedDeviceType = null;

    /**
     * Get the validation rules matching StoreDeviceRequest.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            // Parent references (rack placement is optional)
            'datacenter_name' => ['nullable', 'string', 'max:255'],
            'room_name' => ['nullable', 'string', 'max:255'],
            'row_name' => ['nullable', 'string', 'max:255'],
            'rack_name' => ['nullable', 'string', 'max:255'],

            // Required fields
            'name' => ['required', 'string', 'max:255'],
            'device_type_name' => ['required', 'string', 'max:255'],
            'lifecycle_status' => ['required', 'string'],

            // Optional identification fields
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],

            // Warranty and purchase dates
            'purchase_date' => ['nullable'],
            'warranty_start_date' => ['nullable'],
            'warranty_end_date' => ['nullable'],

            // Physical dimension fields
            'u_height' => ['required', 'numeric', 'min:1', 'max:48'],
            'depth' => ['required', 'string'],
            'width_type' => ['required', 'string'],
            'rack_face' => ['required', 'string'],

            // Placement field (nullable for unplaced inventory)
            'start_u' => ['nullable', 'integer', 'min:1', 'max:48'],

            // Notes
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'The device name is required.',
            'name.string' => 'The device name must be a string.',
            'name.max' => 'The device name must not exceed 255 characters.',
            'device_type_name.required' => 'The device type is required.',
            'lifecycle_status.required' => 'The lifecycle status is required.',
            'serial_number.string' => 'The serial number must be a string.',
            'serial_number.max' => 'The serial number must not exceed 255 characters.',
            'manufacturer.string' => 'The manufacturer must be a string.',
            'manufacturer.max' => 'The manufacturer must not exceed 255 characters.',
            'model.string' => 'The model must be a string.',
            'model.max' => 'The model must not exceed 255 characters.',
            'u_height.required' => 'The U-height is required.',
            'u_height.numeric' => 'The U-height must be a number.',
            'u_height.min' => 'The U-height must be at least 1.',
            'u_height.max' => 'The U-height must not exceed 48.',
            'depth.required' => 'The depth is required.',
            'width_type.required' => 'The width type is required.',
            'rack_face.required' => 'The rack face is required.',
            'start_u.integer' => 'The start U position must be an integer.',
            'start_u.min' => 'The start U position must be at least 1.',
            'start_u.max' => 'The start U position must not exceed 48.',
            'notes.string' => 'The notes must be a string.',
            'notes.max' => 'The notes must not exceed 65535 characters.',
        ];
    }

    /**
     * Run custom validation rules for device import.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    protected function validateCustomRules(array $row, int $rowNumber): array
    {
        $errors = [];

        // Validate device type exists
        $deviceTypeName = $this->getValue($row, 'device_type_name');
        $this->cachedDeviceType = $this->findDeviceType($deviceTypeName);

        if (! $this->cachedDeviceType) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'device_type_name',
                'error_message' => "Device type '{$deviceTypeName}' does not exist.",
            ];
        }

        // Validate lifecycle_status enum
        $lifecycleStatusLabel = $this->getValue($row, 'lifecycle_status');
        $lifecycleStatus = $this->parseDeviceLifecycleStatus($lifecycleStatusLabel);

        if (! $lifecycleStatus && $lifecycleStatusLabel) {
            $validValues = implode(', ', array_map(fn (DeviceLifecycleStatus $s) => $s->label(), DeviceLifecycleStatus::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'lifecycle_status',
                'error_message' => "Invalid lifecycle status '{$lifecycleStatusLabel}'. Valid values are: {$validValues}.",
            ];
        }

        // Validate depth enum
        $depthLabel = $this->getValue($row, 'depth');
        $depth = $this->parseDeviceDepth($depthLabel);

        if (! $depth && $depthLabel) {
            $validValues = implode(', ', array_map(fn (DeviceDepth $d) => $d->label(), DeviceDepth::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'depth',
                'error_message' => "Invalid depth '{$depthLabel}'. Valid values are: {$validValues}.",
            ];
        }

        // Validate width_type enum
        $widthTypeLabel = $this->getValue($row, 'width_type');
        $widthType = $this->parseDeviceWidthType($widthTypeLabel);

        if (! $widthType && $widthTypeLabel) {
            $validValues = implode(', ', array_map(fn (DeviceWidthType $w) => $w->label(), DeviceWidthType::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'width_type',
                'error_message' => "Invalid width type '{$widthTypeLabel}'. Valid values are: {$validValues}.",
            ];
        }

        // Validate rack_face enum
        $rackFaceLabel = $this->getValue($row, 'rack_face');
        $rackFace = $this->parseDeviceRackFace($rackFaceLabel);

        if (! $rackFace && $rackFaceLabel) {
            $validValues = implode(', ', array_map(fn (DeviceRackFace $f) => $f->label(), DeviceRackFace::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'rack_face',
                'error_message' => "Invalid rack face '{$rackFaceLabel}'. Valid values are: {$validValues}.",
            ];
        }

        // Validate rack placement if rack_name is provided
        $rackName = $this->getValue($row, 'rack_name');
        if ($rackName) {
            $errors = array_merge($errors, $this->validateRackPlacement($row, $rowNumber));
        }

        return $errors;
    }

    /**
     * Validate rack placement hierarchy and capacity.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    protected function validateRackPlacement(array $row, int $rowNumber): array
    {
        $errors = [];

        // Validate parent datacenter exists
        $datacenterName = $this->getValue($row, 'datacenter_name');
        $this->cachedDatacenter = $this->findDatacenter($datacenterName);

        if (! $this->cachedDatacenter) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'datacenter_name',
                'error_message' => "Datacenter '{$datacenterName}' does not exist.",
            ];

            return $errors;
        }

        // Validate parent room exists within datacenter
        $roomName = $this->getValue($row, 'room_name');
        $this->cachedRoom = $this->findRoom($roomName, $this->cachedDatacenter);

        if (! $this->cachedRoom) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'room_name',
                'error_message' => "Room '{$roomName}' does not exist in datacenter '{$datacenterName}'.",
            ];

            return $errors;
        }

        // Validate parent row exists within room
        $rowName = $this->getValue($row, 'row_name');
        $this->cachedRow = $this->findRow($rowName, $this->cachedRoom);

        if (! $this->cachedRow) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'row_name',
                'error_message' => "Row '{$rowName}' does not exist in room '{$roomName}'.",
            ];

            return $errors;
        }

        // Validate parent rack exists within row
        $rackName = $this->getValue($row, 'rack_name');
        $this->cachedRack = $this->findRack($rackName, $this->cachedRow);

        if (! $this->cachedRack) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'rack_name',
                'error_message' => "Rack '{$rackName}' does not exist in row '{$rowName}'.",
            ];

            return $errors;
        }

        // Validate device placement against rack capacity
        $startU = $this->getValue($row, 'start_u');
        $uHeight = $this->getValue($row, 'u_height');

        if ($startU !== null && $uHeight !== null) {
            $startUInt = (int) $startU;
            $uHeightInt = (int) $uHeight;
            $rackCapacity = $this->cachedRack->u_height->value;

            // Check if device fits within rack
            $endU = $startUInt + $uHeightInt - 1;

            if ($endU > $rackCapacity) {
                $errors[] = [
                    'row_number' => $rowNumber,
                    'field_name' => 'start_u',
                    'error_message' => "Device placement exceeds rack capacity. Device occupies U{$startUInt} to U{$endU}, but rack has only {$rackCapacity}U.",
                ];
            }
        }

        return $errors;
    }

    /**
     * Create the device from validated data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createEntity(array $data, int $rowNumber): Device
    {
        $lifecycleStatus = $this->parseDeviceLifecycleStatus($this->getValue($data, 'lifecycle_status'));
        $depth = $this->parseDeviceDepth($this->getValue($data, 'depth'));
        $widthType = $this->parseDeviceWidthType($this->getValue($data, 'width_type'));
        $rackFace = $this->parseDeviceRackFace($this->getValue($data, 'rack_face'));

        $startU = $this->getValue($data, 'start_u');

        return Device::create([
            'name' => $this->getValue($data, 'name'),
            'device_type_id' => $this->cachedDeviceType->id,
            'lifecycle_status' => $lifecycleStatus,
            'serial_number' => $this->getValue($data, 'serial_number'),
            'manufacturer' => $this->getValue($data, 'manufacturer'),
            'model' => $this->getValue($data, 'model'),
            'purchase_date' => $this->parseDate($data, 'purchase_date'),
            'warranty_start_date' => $this->parseDate($data, 'warranty_start_date'),
            'warranty_end_date' => $this->parseDate($data, 'warranty_end_date'),
            'u_height' => (float) $this->getValue($data, 'u_height'),
            'depth' => $depth,
            'width_type' => $widthType,
            'rack_face' => $rackFace,
            'rack_id' => $this->cachedRack?->id,
            'start_u' => $startU !== null ? (int) $startU : null,
            'notes' => $this->getValue($data, 'notes'),
        ]);
    }
}

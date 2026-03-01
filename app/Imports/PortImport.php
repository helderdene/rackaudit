<?php

namespace App\Imports;

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\PortVisualFace;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;

/**
 * Import class for Port entities.
 *
 * Handles validation and creation of ports from spreadsheet data.
 * Ports require parent device reference by full path (datacenter > room > row > rack > device).
 */
class PortImport extends AbstractEntityImport
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
     * Cached row for the current port.
     */
    protected ?Row $cachedRow = null;

    /**
     * Cached rack for the current port.
     */
    protected ?Rack $cachedRack = null;

    /**
     * Cached device for the current port.
     */
    protected ?Device $cachedDevice = null;

    /**
     * Cached port type for subtype validation.
     */
    protected ?PortType $cachedPortType = null;

    /**
     * Get the validation rules matching StorePortRequest.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            // Parent references
            'datacenter_name' => ['required', 'string', 'max:255'],
            'room_name' => ['required', 'string', 'max:255'],
            'row_name' => ['required', 'string', 'max:255'],
            'rack_name' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:255'],

            // Required fields
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'subtype' => ['required', 'string'],

            // Optional fields with defaults
            'status' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],

            // Physical position fields
            'position_slot' => ['nullable', 'integer', 'min:0'],
            'position_row' => ['nullable', 'integer', 'min:0'],
            'position_column' => ['nullable', 'integer', 'min:0'],

            // Visual position fields
            'visual_x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'visual_y' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'visual_face' => ['nullable', 'string'],
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
            'datacenter_name.required' => 'The datacenter name is required.',
            'room_name.required' => 'The room name is required.',
            'row_name.required' => 'The row name is required.',
            'rack_name.required' => 'The rack name is required.',
            'device_name.required' => 'The device name is required.',
            'label.required' => 'The port label is required.',
            'label.string' => 'The port label must be a string.',
            'label.max' => 'The port label must not exceed 255 characters.',
            'type.required' => 'The port type is required.',
            'subtype.required' => 'The port subtype is required.',
            'position_slot.integer' => 'The position slot must be an integer.',
            'position_slot.min' => 'The position slot must be at least 0.',
            'position_row.integer' => 'The position row must be an integer.',
            'position_row.min' => 'The position row must be at least 0.',
            'position_column.integer' => 'The position column must be an integer.',
            'position_column.min' => 'The position column must be at least 0.',
            'visual_x.numeric' => 'The visual X position must be a number.',
            'visual_x.min' => 'The visual X position must be at least 0.',
            'visual_x.max' => 'The visual X position must not exceed 100.',
            'visual_y.numeric' => 'The visual Y position must be a number.',
            'visual_y.min' => 'The visual Y position must be at least 0.',
            'visual_y.max' => 'The visual Y position must not exceed 100.',
        ];
    }

    /**
     * Run custom validation rules for port import.
     *
     * @param array<string, mixed> $row
     * @return array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    protected function validateCustomRules(array $row, int $rowNumber): array
    {
        $errors = [];

        // Validate parent hierarchy
        $errors = array_merge($errors, $this->validateDeviceHierarchy($row, $rowNumber));

        // Validate port type enum
        $typeLabel = $this->getValue($row, 'type');
        $this->cachedPortType = $this->parsePortType($typeLabel);

        if (! $this->cachedPortType && $typeLabel) {
            $validValues = implode(', ', array_map(fn (PortType $t) => $t->label(), PortType::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'type',
                'error_message' => "Invalid port type '{$typeLabel}'. Valid values are: {$validValues}.",
            ];
        }

        // Validate port subtype enum
        $subtypeLabel = $this->getValue($row, 'subtype');
        $subtype = $this->parsePortSubtype($subtypeLabel);

        if (! $subtype && $subtypeLabel) {
            $validValues = implode(', ', array_map(fn (PortSubtype $s) => $s->label(), PortSubtype::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'subtype',
                'error_message' => "Invalid port subtype '{$subtypeLabel}'. Valid values are: {$validValues}.",
            ];
        }

        // Validate subtype is compatible with type
        if ($this->cachedPortType && $subtype) {
            $validSubtypes = PortSubtype::forType($this->cachedPortType);
            if (! in_array($subtype, $validSubtypes, true)) {
                $validLabels = implode(', ', array_map(fn (PortSubtype $s) => $s->label(), $validSubtypes));
                $errors[] = [
                    'row_number' => $rowNumber,
                    'field_name' => 'subtype',
                    'error_message' => "Port subtype '{$subtypeLabel}' is not valid for port type '{$this->cachedPortType->label()}'. Valid subtypes are: {$validLabels}.",
                ];
            }
        }

        // Validate port status enum (if provided)
        $statusLabel = $this->getValue($row, 'status');
        if ($statusLabel) {
            $status = $this->parsePortStatus($statusLabel);
            if (! $status) {
                $validValues = implode(', ', array_map(fn (PortStatus $s) => $s->label(), PortStatus::cases()));
                $errors[] = [
                    'row_number' => $rowNumber,
                    'field_name' => 'status',
                    'error_message' => "Invalid port status '{$statusLabel}'. Valid values are: {$validValues}.",
                ];
            }
        }

        // Validate port direction enum (if provided)
        $directionLabel = $this->getValue($row, 'direction');
        if ($directionLabel) {
            $direction = $this->parsePortDirection($directionLabel);
            if (! $direction) {
                $validValues = implode(', ', array_map(fn (PortDirection $d) => $d->label(), PortDirection::cases()));
                $errors[] = [
                    'row_number' => $rowNumber,
                    'field_name' => 'direction',
                    'error_message' => "Invalid port direction '{$directionLabel}'. Valid values are: {$validValues}.",
                ];
            }

            // Validate direction is compatible with type
            if ($this->cachedPortType && $direction) {
                $validDirections = PortDirection::forType($this->cachedPortType);
                if (! in_array($direction, $validDirections, true)) {
                    $validLabels = implode(', ', array_map(fn (PortDirection $d) => $d->label(), $validDirections));
                    $errors[] = [
                        'row_number' => $rowNumber,
                        'field_name' => 'direction',
                        'error_message' => "Port direction '{$directionLabel}' is not valid for port type '{$this->cachedPortType->label()}'. Valid directions are: {$validLabels}.",
                    ];
                }
            }
        }

        // Validate visual face enum (if provided)
        $visualFaceLabel = $this->getValue($row, 'visual_face');
        if ($visualFaceLabel) {
            $visualFace = $this->parsePortVisualFace($visualFaceLabel);
            if (! $visualFace) {
                $validValues = implode(', ', array_map(fn (PortVisualFace $f) => $f->label(), PortVisualFace::cases()));
                $errors[] = [
                    'row_number' => $rowNumber,
                    'field_name' => 'visual_face',
                    'error_message' => "Invalid visual face '{$visualFaceLabel}'. Valid values are: {$validValues}.",
                ];
            }
        }

        return $errors;
    }

    /**
     * Validate device hierarchy for port placement.
     *
     * @param array<string, mixed> $row
     * @return array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    protected function validateDeviceHierarchy(array $row, int $rowNumber): array
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

        // Validate parent device exists within rack
        $deviceName = $this->getValue($row, 'device_name');
        $this->cachedDevice = $this->findDevice($deviceName, $this->cachedRack);

        if (! $this->cachedDevice) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'device_name',
                'error_message' => "Device '{$deviceName}' does not exist in rack '{$rackName}'.",
            ];
        }

        return $errors;
    }

    /**
     * Create the port from validated data.
     *
     * @param array<string, mixed> $data
     */
    protected function createEntity(array $data, int $rowNumber): Port
    {
        $subtype = $this->parsePortSubtype($this->getValue($data, 'subtype'));
        $status = $this->parsePortStatus($this->getValue($data, 'status'));
        $direction = $this->parsePortDirection($this->getValue($data, 'direction'));
        $visualFace = $this->parsePortVisualFace($this->getValue($data, 'visual_face'));

        return Port::create([
            'device_id' => $this->cachedDevice->id,
            'label' => $this->getValue($data, 'label'),
            'type' => $this->cachedPortType,
            'subtype' => $subtype,
            'status' => $status ?? PortStatus::Available,
            'direction' => $direction,
            'position_slot' => $this->getValue($data, 'position_slot'),
            'position_row' => $this->getValue($data, 'position_row'),
            'position_column' => $this->getValue($data, 'position_column'),
            'visual_x' => $this->getValue($data, 'visual_x'),
            'visual_y' => $this->getValue($data, 'visual_y'),
            'visual_face' => $visualFace,
        ]);
    }
}

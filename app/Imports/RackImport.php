<?php

namespace App\Imports;

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Datacenter;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;

/**
 * Import class for Rack entities.
 *
 * Handles validation and creation of racks from spreadsheet data.
 * Racks require parent datacenter > room > row reference by name.
 */
class RackImport extends AbstractEntityImport
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
     * Cached row for the current rack.
     */
    protected ?Row $cachedRow = null;

    /**
     * Get the validation rules matching StoreRackRequest.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'datacenter_name' => ['required', 'string', 'max:255'],
            'room_name' => ['required', 'string', 'max:255'],
            'row_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'integer', 'min:0'],
            'u_height' => ['required'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string'],
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
            'name.required' => 'The rack name is required.',
            'name.string' => 'The rack name must be a string.',
            'name.max' => 'The rack name must not exceed 255 characters.',
            'position.required' => 'The rack position is required.',
            'position.integer' => 'The rack position must be an integer.',
            'position.min' => 'The rack position must be at least 0.',
            'u_height.required' => 'The rack U-height is required.',
            'serial_number.string' => 'The serial number must be a string.',
            'serial_number.max' => 'The serial number must not exceed 255 characters.',
            'status.required' => 'The rack status is required.',
        ];
    }

    /**
     * Run custom validation rules for rack import.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    protected function validateCustomRules(array $row, int $rowNumber): array
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
        }

        // Validate parent room exists within datacenter
        $roomName = $this->getValue($row, 'room_name');
        $this->cachedRoom = $this->findRoom($roomName, $this->cachedDatacenter);

        if ($this->cachedDatacenter && ! $this->cachedRoom) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'room_name',
                'error_message' => "Room '{$roomName}' does not exist in datacenter '{$datacenterName}'.",
            ];
        }

        // Validate parent row exists within room
        $rowName = $this->getValue($row, 'row_name');
        $this->cachedRow = $this->findRow($rowName, $this->cachedRoom);

        if ($this->cachedRoom && ! $this->cachedRow) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'row_name',
                'error_message' => "Row '{$rowName}' does not exist in room '{$roomName}'.",
            ];
        }

        // Validate u_height enum
        $uHeightLabel = $this->getValue($row, 'u_height');
        $uHeight = $this->parseRackUHeight((string) $uHeightLabel);

        if (! $uHeight && $uHeightLabel !== null) {
            $validValues = implode(', ', array_map(fn (RackUHeight $h) => $h->label(), RackUHeight::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'u_height',
                'error_message' => "Invalid rack U-height '{$uHeightLabel}'. Valid values are: {$validValues}.",
            ];
        }

        // Validate status enum
        $statusLabel = $this->getValue($row, 'status');
        $status = $this->parseRackStatus($statusLabel);

        if (! $status && $statusLabel) {
            $validValues = implode(', ', array_map(fn (RackStatus $s) => $s->label(), RackStatus::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'status',
                'error_message' => "Invalid rack status '{$statusLabel}'. Valid values are: {$validValues}.",
            ];
        }

        return $errors;
    }

    /**
     * Create the rack from validated data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createEntity(array $data, int $rowNumber): Rack
    {
        $uHeight = $this->parseRackUHeight((string) $this->getValue($data, 'u_height'));
        $status = $this->parseRackStatus($this->getValue($data, 'status'));

        return Rack::create([
            'row_id' => $this->cachedRow->id,
            'name' => $this->getValue($data, 'name'),
            'position' => (int) $this->getValue($data, 'position'),
            'u_height' => $uHeight,
            'serial_number' => $this->getValue($data, 'serial_number'),
            'status' => $status,
        ]);
    }
}

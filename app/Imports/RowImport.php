<?php

namespace App\Imports;

use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Models\Datacenter;
use App\Models\Room;
use App\Models\Row;

/**
 * Import class for Row entities.
 *
 * Handles validation and creation of rows from spreadsheet data.
 * Rows require parent datacenter > room reference by name.
 */
class RowImport extends AbstractEntityImport
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
     * Get the validation rules matching StoreRowRequest.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'datacenter_name' => ['required', 'string', 'max:255'],
            'room_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'integer', 'min:0'],
            'orientation' => ['required', 'string'],
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
            'name.required' => 'The row name is required.',
            'name.string' => 'The row name must be a string.',
            'name.max' => 'The row name must not exceed 255 characters.',
            'position.required' => 'The row position is required.',
            'position.integer' => 'The row position must be an integer.',
            'position.min' => 'The row position must be at least 0.',
            'orientation.required' => 'The row orientation is required.',
            'status.required' => 'The row status is required.',
        ];
    }

    /**
     * Run custom validation rules for row import.
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

        // Validate orientation enum
        $orientationLabel = $this->getValue($row, 'orientation');
        $orientation = $this->parseRowOrientation($orientationLabel);

        if (! $orientation && $orientationLabel) {
            $validValues = implode(', ', array_map(fn (RowOrientation $o) => $o->label(), RowOrientation::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'orientation',
                'error_message' => "Invalid row orientation '{$orientationLabel}'. Valid values are: {$validValues}.",
            ];
        }

        // Validate status enum
        $statusLabel = $this->getValue($row, 'status');
        $status = $this->parseRowStatus($statusLabel);

        if (! $status && $statusLabel) {
            $validValues = implode(', ', array_map(fn (RowStatus $s) => $s->label(), RowStatus::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'status',
                'error_message' => "Invalid row status '{$statusLabel}'. Valid values are: {$validValues}.",
            ];
        }

        return $errors;
    }

    /**
     * Create the row from validated data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createEntity(array $data, int $rowNumber): Row
    {
        $orientation = $this->parseRowOrientation($this->getValue($data, 'orientation'));
        $status = $this->parseRowStatus($this->getValue($data, 'status'));

        return Row::create([
            'room_id' => $this->cachedRoom->id,
            'name' => $this->getValue($data, 'name'),
            'position' => (int) $this->getValue($data, 'position'),
            'orientation' => $orientation,
            'status' => $status,
        ]);
    }
}

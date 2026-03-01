<?php

namespace App\Imports;

use App\Enums\RoomType;
use App\Models\Datacenter;
use App\Models\Room;

/**
 * Import class for Room entities.
 *
 * Handles validation and creation of rooms from spreadsheet data.
 * Rooms require a parent datacenter reference by name.
 */
class RoomImport extends AbstractEntityImport
{
    /**
     * Cached datacenter for the current import.
     */
    protected ?Datacenter $cachedDatacenter = null;

    /**
     * Get the validation rules matching StoreRoomRequest.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'datacenter_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'square_footage' => ['nullable', 'numeric', 'min:0'],
            'type' => ['required', 'string'],
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
            'datacenter_name.string' => 'The datacenter name must be a string.',
            'datacenter_name.max' => 'The datacenter name must not exceed 255 characters.',
            'name.required' => 'The room name is required.',
            'name.string' => 'The room name must be a string.',
            'name.max' => 'The room name must not exceed 255 characters.',
            'description.string' => 'The description must be a string.',
            'square_footage.numeric' => 'The square footage must be a number.',
            'square_footage.min' => 'The square footage must be at least 0.',
            'type.required' => 'The room type is required.',
        ];
    }

    /**
     * Run custom validation rules for room import.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    protected function validateCustomRules(array $row, int $rowNumber): array
    {
        $errors = [];

        // Validate parent datacenter exists
        $datacenterName = $this->getValue($row, 'datacenter_name');
        $datacenter = $this->findDatacenter($datacenterName);

        if (! $datacenter) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'datacenter_name',
                'error_message' => "Datacenter '{$datacenterName}' does not exist.",
            ];
        } else {
            $this->cachedDatacenter = $datacenter;
        }

        // Validate room type enum
        $typeLabel = $this->getValue($row, 'type');
        $roomType = $this->parseRoomType($typeLabel);

        if (! $roomType && $typeLabel) {
            $validTypes = implode(', ', array_map(fn (RoomType $t) => $t->label(), RoomType::cases()));
            $errors[] = [
                'row_number' => $rowNumber,
                'field_name' => 'type',
                'error_message' => "Invalid room type '{$typeLabel}'. Valid types are: {$validTypes}.",
            ];
        }

        return $errors;
    }

    /**
     * Create the room from validated data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createEntity(array $data, int $rowNumber): Room
    {
        $roomType = $this->parseRoomType($this->getValue($data, 'type'));

        return Room::create([
            'datacenter_id' => $this->cachedDatacenter->id,
            'name' => $this->getValue($data, 'name'),
            'description' => $this->getValue($data, 'description'),
            'square_footage' => $this->getValue($data, 'square_footage'),
            'type' => $roomType,
        ]);
    }
}

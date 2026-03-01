<?php

namespace App\Imports;

use App\Enums\DeviceDepth;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\PortVisualFace;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Enums\RoomType;
use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Abstract base class for entity imports.
 *
 * Provides common functionality for validating and importing
 * datacenter infrastructure entities from spreadsheet files.
 */
abstract class AbstractEntityImport implements ToCollection, WithHeadingRow
{
    /**
     * Collection of errors encountered during import.
     *
     * @var array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    protected array $errors = [];

    /**
     * Collection of successfully created entities.
     *
     * @var array<int, mixed>
     */
    protected array $createdEntities = [];

    /**
     * Number of successfully processed rows.
     */
    protected int $successCount = 0;

    /**
     * Number of failed rows.
     */
    protected int $failureCount = 0;

    /**
     * Get the validation rules for the entity.
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    abstract protected function messages(): array;

    /**
     * Create the entity from validated data.
     *
     * @param  array<string, mixed>  $data
     */
    abstract protected function createEntity(array $data, int $rowNumber): mixed;

    /**
     * Process the collection of rows from the spreadsheet.
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because index is 0-based and we skip header row
            $rowArray = is_array($row) ? $row : $row->toArray();
            $this->processRow($rowArray, $rowNumber);
        }
    }

    /**
     * Process a single row from the import.
     *
     * @param  array<string, mixed>  $row
     * @return array{success: bool, entity: mixed|null, errors: array<int, array{row_number: int, field_name: string, error_message: string}>}
     */
    public function processRow(array $row, int $rowNumber): array
    {
        $rowErrors = [];

        // Validate the row data
        $validator = Validator::make($row, $this->rules(), $this->messages());

        if ($validator->fails()) {
            foreach ($validator->errors()->toArray() as $field => $messages) {
                foreach ($messages as $message) {
                    $error = [
                        'row_number' => $rowNumber,
                        'field_name' => $field,
                        'error_message' => $message,
                    ];
                    $rowErrors[] = $error;
                    $this->errors[] = $error;
                }
            }
        }

        // Run additional custom validations
        $customErrors = $this->validateCustomRules($row, $rowNumber);
        foreach ($customErrors as $error) {
            $rowErrors[] = $error;
            $this->errors[] = $error;
        }

        if (! empty($rowErrors)) {
            $this->failureCount++;

            return [
                'success' => false,
                'entity' => null,
                'errors' => $rowErrors,
            ];
        }

        // Create the entity
        try {
            $entity = $this->createEntity($row, $rowNumber);
            $this->createdEntities[] = $entity;
            $this->successCount++;

            return [
                'success' => true,
                'entity' => $entity,
                'errors' => [],
            ];
        } catch (\Exception $e) {
            $error = [
                'row_number' => $rowNumber,
                'field_name' => 'general',
                'error_message' => 'Failed to create entity: '.$e->getMessage(),
            ];
            $this->errors[] = $error;
            $this->failureCount++;

            return [
                'success' => false,
                'entity' => null,
                'errors' => [$error],
            ];
        }
    }

    /**
     * Run custom validation rules beyond standard Laravel validation.
     *
     * Override this method in subclasses to add entity-specific validation.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    protected function validateCustomRules(array $row, int $rowNumber): array
    {
        return [];
    }

    /**
     * Get all collected errors.
     *
     * @return array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all created entities.
     *
     * @return array<int, mixed>
     */
    public function getCreatedEntities(): array
    {
        return $this->createdEntities;
    }

    /**
     * Get the success count.
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get the failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Look up a datacenter by name.
     */
    protected function findDatacenter(?string $name): ?Datacenter
    {
        if (empty($name)) {
            return null;
        }

        return Datacenter::query()
            ->where('name', $name)
            ->first();
    }

    /**
     * Look up a room by name within a specific datacenter.
     */
    protected function findRoom(?string $roomName, ?Datacenter $datacenter): ?Room
    {
        if (empty($roomName) || ! $datacenter) {
            return null;
        }

        return Room::query()
            ->where('name', $roomName)
            ->where('datacenter_id', $datacenter->id)
            ->first();
    }

    /**
     * Look up a row by name within a specific room.
     */
    protected function findRow(?string $rowName, ?Room $room): ?Row
    {
        if (empty($rowName) || ! $room) {
            return null;
        }

        return Row::query()
            ->where('name', $rowName)
            ->where('room_id', $room->id)
            ->first();
    }

    /**
     * Look up a rack by name within a specific row.
     */
    protected function findRack(?string $rackName, ?Row $row): ?Rack
    {
        if (empty($rackName) || ! $row) {
            return null;
        }

        return Rack::query()
            ->where('name', $rackName)
            ->where('row_id', $row->id)
            ->first();
    }

    /**
     * Look up a device by name within a specific rack.
     */
    protected function findDevice(?string $deviceName, ?Rack $rack): ?Device
    {
        if (empty($deviceName) || ! $rack) {
            return null;
        }

        return Device::query()
            ->where('name', $deviceName)
            ->where('rack_id', $rack->id)
            ->first();
    }

    /**
     * Look up a device type by name.
     */
    protected function findDeviceType(?string $name): ?DeviceType
    {
        if (empty($name)) {
            return null;
        }

        return DeviceType::query()
            ->where('name', $name)
            ->first();
    }

    /**
     * Parse an enum value from a label string.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T|null
     */
    protected function parseEnumFromLabel(string $enumClass, ?string $label): mixed
    {
        if (empty($label)) {
            return null;
        }

        $label = trim($label);

        foreach ($enumClass::cases() as $case) {
            // Check by label (human-readable)
            if (method_exists($case, 'label') && strcasecmp($case->label(), $label) === 0) {
                return $case;
            }
            // Check by value
            if (strcasecmp((string) $case->value, $label) === 0) {
                return $case;
            }
            // Check by name
            if (strcasecmp($case->name, $label) === 0) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Parse RoomType from label.
     */
    protected function parseRoomType(?string $label): ?RoomType
    {
        return $this->parseEnumFromLabel(RoomType::class, $label);
    }

    /**
     * Parse RowOrientation from label.
     */
    protected function parseRowOrientation(?string $label): ?RowOrientation
    {
        return $this->parseEnumFromLabel(RowOrientation::class, $label);
    }

    /**
     * Parse RowStatus from label.
     */
    protected function parseRowStatus(?string $label): ?RowStatus
    {
        return $this->parseEnumFromLabel(RowStatus::class, $label);
    }

    /**
     * Parse RackStatus from label.
     */
    protected function parseRackStatus(?string $label): ?RackStatus
    {
        return $this->parseEnumFromLabel(RackStatus::class, $label);
    }

    /**
     * Parse RackUHeight from label or value.
     */
    protected function parseRackUHeight(?string $label): ?RackUHeight
    {
        if (empty($label)) {
            return null;
        }

        $label = trim($label);

        // Handle numeric values
        if (is_numeric($label)) {
            $intValue = (int) $label;

            return RackUHeight::tryFrom($intValue);
        }

        // Handle labels like "42U"
        return $this->parseEnumFromLabel(RackUHeight::class, $label);
    }

    /**
     * Parse DeviceLifecycleStatus from label.
     */
    protected function parseDeviceLifecycleStatus(?string $label): ?DeviceLifecycleStatus
    {
        return $this->parseEnumFromLabel(DeviceLifecycleStatus::class, $label);
    }

    /**
     * Parse DeviceDepth from label.
     */
    protected function parseDeviceDepth(?string $label): ?DeviceDepth
    {
        return $this->parseEnumFromLabel(DeviceDepth::class, $label);
    }

    /**
     * Parse DeviceWidthType from label.
     */
    protected function parseDeviceWidthType(?string $label): ?DeviceWidthType
    {
        return $this->parseEnumFromLabel(DeviceWidthType::class, $label);
    }

    /**
     * Parse DeviceRackFace from label.
     */
    protected function parseDeviceRackFace(?string $label): ?DeviceRackFace
    {
        return $this->parseEnumFromLabel(DeviceRackFace::class, $label);
    }

    /**
     * Parse PortType from label.
     */
    protected function parsePortType(?string $label): ?PortType
    {
        return $this->parseEnumFromLabel(PortType::class, $label);
    }

    /**
     * Parse PortSubtype from label.
     */
    protected function parsePortSubtype(?string $label): ?PortSubtype
    {
        return $this->parseEnumFromLabel(PortSubtype::class, $label);
    }

    /**
     * Parse PortStatus from label.
     */
    protected function parsePortStatus(?string $label): ?PortStatus
    {
        return $this->parseEnumFromLabel(PortStatus::class, $label);
    }

    /**
     * Parse PortDirection from label.
     */
    protected function parsePortDirection(?string $label): ?PortDirection
    {
        return $this->parseEnumFromLabel(PortDirection::class, $label);
    }

    /**
     * Parse PortVisualFace from label.
     */
    protected function parsePortVisualFace(?string $label): ?PortVisualFace
    {
        return $this->parseEnumFromLabel(PortVisualFace::class, $label);
    }

    /**
     * Get a value from a row, returning null for empty strings.
     *
     * @param  array<string, mixed>  $row
     */
    protected function getValue(array $row, string $key): mixed
    {
        $value = $row[$key] ?? null;

        if ($value === '' || $value === null) {
            return null;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Parse a date value from a row.
     *
     * @param  array<string, mixed>  $row
     */
    protected function parseDate(array $row, string $key): ?string
    {
        $value = $this->getValue($row, $key);

        if ($value === null) {
            return null;
        }

        // Handle Excel date serial numbers
        if (is_numeric($value)) {
            $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float) $value);

            return date('Y-m-d', $timestamp);
        }

        // Handle string dates
        try {
            $date = new \DateTime((string) $value);

            return $date->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}

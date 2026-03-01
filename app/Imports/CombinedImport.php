<?php

namespace App\Imports;

use App\Enums\BulkImportEntityType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import class for mixed entity imports.
 *
 * Handles detection and routing of rows to appropriate entity import classes
 * based on the filled columns in each row.
 */
class CombinedImport implements ToCollection, WithHeadingRow
{
    /**
     * Entity import instances.
     *
     * @var array<string, AbstractEntityImport>
     */
    protected array $importers;

    /**
     * Collection of all errors from all importers.
     *
     * @var array<int, array{row_number: int, field_name: string, error_message: string}>
     */
    protected array $errors = [];

    /**
     * Collection of all created entities by type.
     *
     * @var array<string, array<int, mixed>>
     */
    protected array $createdEntities = [];

    /**
     * Count of successful imports by type.
     *
     * @var array<string, int>
     */
    protected array $successCounts = [];

    /**
     * Count of failed imports by type.
     *
     * @var array<string, int>
     */
    protected array $failureCounts = [];

    /**
     * Create new CombinedImport instance.
     */
    public function __construct()
    {
        $this->importers = [
            BulkImportEntityType::Datacenter->value => new DatacenterImport,
            BulkImportEntityType::Room->value => new RoomImport,
            BulkImportEntityType::Row->value => new RowImport,
            BulkImportEntityType::Rack->value => new RackImport,
            BulkImportEntityType::Device->value => new DeviceImport,
            BulkImportEntityType::Port->value => new PortImport,
        ];

        foreach (array_keys($this->importers) as $type) {
            $this->createdEntities[$type] = [];
            $this->successCounts[$type] = 0;
            $this->failureCounts[$type] = 0;
        }
    }

    /**
     * Process the collection of rows from the spreadsheet.
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because index is 0-based and we skip header row
            $rowArray = is_array($row) ? $row : $row->toArray();

            // Detect entity type based on filled columns
            $entityType = $this->detectEntityType($rowArray);

            if ($entityType === null) {
                $this->errors[] = [
                    'row_number' => $rowNumber,
                    'field_name' => 'general',
                    'error_message' => 'Unable to determine entity type from row data.',
                ];

                continue;
            }

            // Process the row with the appropriate importer
            $importer = $this->importers[$entityType];
            $result = $importer->processRow($rowArray, $rowNumber);

            if ($result['success']) {
                $this->createdEntities[$entityType][] = $result['entity'];
                $this->successCounts[$entityType]++;
            } else {
                $this->errors = array_merge($this->errors, $result['errors']);
                $this->failureCounts[$entityType]++;
            }
        }
    }

    /**
     * Detect the entity type based on which columns are filled.
     *
     * Priority is from most specific (Port) to least specific (Datacenter).
     *
     * @param  array<string, mixed>  $row
     */
    protected function detectEntityType(array $row): ?string
    {
        // Port: has device_name, label, type, subtype
        if ($this->hasValue($row, 'label') && $this->hasValue($row, 'type') && $this->hasValue($row, 'subtype') && $this->hasValue($row, 'device_name')) {
            return BulkImportEntityType::Port->value;
        }

        // Device: has device_type_name, lifecycle_status, u_height
        if ($this->hasValue($row, 'device_type_name') && $this->hasValue($row, 'lifecycle_status')) {
            return BulkImportEntityType::Device->value;
        }

        // Rack: has row_name, position, u_height (for rack), status
        if ($this->hasValue($row, 'row_name') && $this->hasValue($row, 'u_height') && $this->hasValue($row, 'status') && ! $this->hasValue($row, 'device_type_name')) {
            return BulkImportEntityType::Rack->value;
        }

        // Row: has room_name, position, orientation, status
        if ($this->hasValue($row, 'room_name') && $this->hasValue($row, 'orientation') && $this->hasValue($row, 'status')) {
            return BulkImportEntityType::Row->value;
        }

        // Room: has datacenter_name, name, type (room type)
        if ($this->hasValue($row, 'datacenter_name') && $this->hasValue($row, 'name') && $this->hasValue($row, 'type') && ! $this->hasValue($row, 'subtype')) {
            return BulkImportEntityType::Room->value;
        }

        // Datacenter: has name, address_line_1, city, country, primary_contact_name
        if ($this->hasValue($row, 'name') && $this->hasValue($row, 'address_line_1') && $this->hasValue($row, 'city') && $this->hasValue($row, 'primary_contact_name')) {
            return BulkImportEntityType::Datacenter->value;
        }

        return null;
    }

    /**
     * Check if a column has a non-empty value.
     *
     * @param  array<string, mixed>  $row
     */
    protected function hasValue(array $row, string $key): bool
    {
        return isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null;
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
     * Get all created entities grouped by type.
     *
     * @return array<string, array<int, mixed>>
     */
    public function getCreatedEntities(): array
    {
        return $this->createdEntities;
    }

    /**
     * Get created entities for a specific type.
     *
     * @return array<int, mixed>
     */
    public function getCreatedEntitiesForType(string $type): array
    {
        return $this->createdEntities[$type] ?? [];
    }

    /**
     * Get success counts by type.
     *
     * @return array<string, int>
     */
    public function getSuccessCounts(): array
    {
        return $this->successCounts;
    }

    /**
     * Get failure counts by type.
     *
     * @return array<string, int>
     */
    public function getFailureCounts(): array
    {
        return $this->failureCounts;
    }

    /**
     * Get total success count across all types.
     */
    public function getTotalSuccessCount(): int
    {
        return array_sum($this->successCounts);
    }

    /**
     * Get total failure count across all types.
     */
    public function getTotalFailureCount(): int
    {
        return array_sum($this->failureCounts);
    }

    /**
     * Get the individual importers for direct access.
     *
     * @return array<string, AbstractEntityImport>
     */
    public function getImporters(): array
    {
        return $this->importers;
    }
}

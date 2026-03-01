<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import class for parsing connection files (Excel and CSV).
 *
 * Extracts connection data from uploaded implementation files
 * and validates the structure before parsing individual rows.
 */
class ConnectionFileImport implements ToCollection, WithHeadingRow
{
    /**
     * Required column headers that must be present.
     *
     * @var array<string>
     */
    protected const REQUIRED_COLUMNS = [
        'Source Device',
        'Source Port',
        'Dest Device',
        'Dest Port',
    ];

    /**
     * All expected column headers.
     *
     * @var array<string>
     */
    protected const ALL_COLUMNS = [
        'Source Device',
        'Source Port',
        'Dest Device',
        'Dest Port',
        'Cable Type',
        'Cable Length',
    ];

    /**
     * Mapping of header names to normalized keys.
     *
     * @var array<string, string>
     */
    protected const COLUMN_MAPPING = [
        'Source Device' => 'source_device',
        'Source Port' => 'source_port',
        'Dest Device' => 'dest_device',
        'Dest Port' => 'dest_port',
        'Cable Type' => 'cable_type',
        'Cable Length' => 'cable_length',
    ];

    /**
     * The file path to parse.
     */
    protected ?string $filePath = null;

    /**
     * The file type (xlsx, xls, csv).
     */
    protected string $fileType = 'xlsx';

    /**
     * Parsed rows from the file.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $parsedRows = [];

    /**
     * Validation errors per row.
     *
     * @var array<int, array{row_number: int, field: string, message: string}>
     */
    protected array $rowErrors = [];

    /**
     * Global parsing errors.
     *
     * @var array<string>
     */
    protected array $errors = [];

    /**
     * Headers found in the file.
     *
     * @var array<string>
     */
    protected array $foundHeaders = [];

    /**
     * Load a file for parsing.
     */
    public function loadFile(string $filePath, ?string $fileType = null): self
    {
        $this->filePath = $filePath;

        if ($fileType) {
            $this->fileType = strtolower($fileType);
        } else {
            // Determine type from extension
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $this->fileType = $extension ?: 'xlsx';
        }

        return $this;
    }

    /**
     * Parse the loaded file.
     *
     * @return array{success: bool, rows: array<int, array<string, mixed>>, row_errors: array<int, array{row_number: int, field: string, message: string}>, errors: array<string>}
     */
    public function parse(): array
    {
        if (! $this->filePath) {
            return [
                'success' => false,
                'rows' => [],
                'row_errors' => [],
                'errors' => ['No file loaded for parsing.'],
            ];
        }

        $this->parsedRows = [];
        $this->rowErrors = [];
        $this->errors = [];

        try {
            if ($this->fileType === 'csv') {
                $this->parseCsv();
            } else {
                Excel::import($this, $this->filePath);
            }
        } catch (\Exception $e) {
            $this->errors[] = 'Failed to parse file: '.$e->getMessage();

            return [
                'success' => false,
                'rows' => [],
                'row_errors' => [],
                'errors' => $this->errors,
            ];
        }

        return [
            'success' => empty($this->errors),
            'rows' => $this->parsedRows,
            'row_errors' => $this->rowErrors,
            'errors' => $this->errors,
        ];
    }

    /**
     * Process the collection from Excel import.
     */
    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'File is empty or has no data rows.';

            return;
        }

        // Get headers from first row keys
        $firstRow = $rows->first();
        if ($firstRow) {
            $this->foundHeaders = array_keys($firstRow->toArray());
            $this->validateHeaders();
        }

        if (! empty($this->errors)) {
            return;
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because index is 0-based and we skip header row
            $this->processRow($row->toArray(), $rowNumber);
        }
    }

    /**
     * Parse a CSV file directly.
     */
    protected function parseCsv(): void
    {
        $handle = fopen($this->filePath, 'r');
        if (! $handle) {
            $this->errors[] = 'Failed to open file for reading.';

            return;
        }

        // Read headers
        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            $this->errors[] = 'File is empty or has no header row.';

            return;
        }

        // Trim headers
        $headers = array_map('trim', $headers);
        $this->foundHeaders = $headers;

        // Validate headers
        $this->validateHeaders();
        if (! empty($this->errors)) {
            fclose($handle);

            return;
        }

        // Process data rows
        $rowNumber = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $row = array_combine($headers, array_map('trim', $data));
            $this->processRow($row, $rowNumber);
        }

        fclose($handle);
    }

    /**
     * Validate that required headers are present.
     */
    protected function validateHeaders(): void
    {
        // Normalize found headers for comparison
        $normalizedHeaders = array_map(function ($header) {
            return $this->normalizeHeader($header);
        }, $this->foundHeaders);

        foreach (self::REQUIRED_COLUMNS as $requiredColumn) {
            $normalizedRequired = $this->normalizeHeader($requiredColumn);
            if (! in_array($normalizedRequired, $normalizedHeaders, true)) {
                $this->errors[] = "Missing required column: {$requiredColumn}";
            }
        }
    }

    /**
     * Normalize a header string for comparison.
     */
    protected function normalizeHeader(string $header): string
    {
        // Convert to lowercase and normalize whitespace
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[\s_-]+/', '_', $normalized);

        return $normalized;
    }

    /**
     * Process a single row of data.
     *
     * @param  array<string, mixed>  $row
     */
    protected function processRow(array $row, int $rowNumber): void
    {
        // Normalize row keys
        $normalizedRow = $this->normalizeRowKeys($row);

        // Skip empty rows
        if ($this->isEmptyRow($normalizedRow)) {
            return;
        }

        // Extract values with validation
        $parsedRow = [
            'row_number' => $rowNumber,
            'source_device' => $this->getValue($normalizedRow, 'source_device'),
            'source_port' => $this->getValue($normalizedRow, 'source_port'),
            'dest_device' => $this->getValue($normalizedRow, 'dest_device'),
            'dest_port' => $this->getValue($normalizedRow, 'dest_port'),
            'cable_type' => $this->getValue($normalizedRow, 'cable_type'),
            'cable_length' => $this->getValue($normalizedRow, 'cable_length'),
        ];

        // Validate required fields
        if (empty($parsedRow['source_device'])) {
            $this->rowErrors[] = [
                'row_number' => $rowNumber,
                'field' => 'source_device',
                'message' => 'Source device is required.',
            ];
        }

        if (empty($parsedRow['source_port'])) {
            $this->rowErrors[] = [
                'row_number' => $rowNumber,
                'field' => 'source_port',
                'message' => 'Source port is required.',
            ];
        }

        if (empty($parsedRow['dest_device'])) {
            $this->rowErrors[] = [
                'row_number' => $rowNumber,
                'field' => 'dest_device',
                'message' => 'Destination device is required.',
            ];
        }

        if (empty($parsedRow['dest_port'])) {
            $this->rowErrors[] = [
                'row_number' => $rowNumber,
                'field' => 'dest_port',
                'message' => 'Destination port is required.',
            ];
        }

        $this->parsedRows[] = $parsedRow;
    }

    /**
     * Normalize row keys to match expected format.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeRowKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalizedKey = $this->normalizeHeader($key);

            // Map to standard key names
            foreach (self::COLUMN_MAPPING as $originalHeader => $mappedKey) {
                if ($normalizedKey === $this->normalizeHeader($originalHeader)) {
                    $normalized[$mappedKey] = $value;
                    break;
                }
            }
        }

        return $normalized;
    }

    /**
     * Check if a row is empty (all values are empty).
     *
     * @param  array<string, mixed>  $row
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (! empty($value) && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a value from the row, returning null for empty values.
     *
     * @param  array<string, mixed>  $row
     */
    protected function getValue(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? trim($value) : (string) $value;
    }

    /**
     * Get all parsed rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getParsedRows(): array
    {
        return $this->parsedRows;
    }

    /**
     * Get all row errors.
     *
     * @return array<int, array{row_number: int, field: string, message: string}>
     */
    public function getRowErrors(): array
    {
        return $this->rowErrors;
    }

    /**
     * Get global errors.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

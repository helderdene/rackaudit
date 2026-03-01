<?php

namespace App\Services;

use App\Enums\BulkImportEntityType;
use App\Enums\BulkImportStatus;
use App\Jobs\ProcessBulkImportJob;
use App\Models\BulkImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service for managing bulk imports.
 *
 * Handles file upload validation, row counting for sync/async determination,
 * BulkImport record creation, and job dispatching.
 */
class BulkImportService
{
    /**
     * Threshold for async processing (rows >= this will be processed asynchronously).
     */
    protected int $asyncThreshold = 100;

    /**
     * Maximum file size in bytes (10MB).
     */
    protected int $maxFileSize = 10 * 1024 * 1024;

    /**
     * Allowed file extensions.
     *
     * @var array<string>
     */
    protected array $allowedExtensions = ['csv', 'xlsx'];

    /**
     * Allowed MIME types.
     *
     * @var array<string>
     */
    protected array $allowedMimeTypes = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Process an uploaded file for import.
     */
    public function handleUpload(UploadedFile $file, User $user, ?BulkImportEntityType $entityType = null): BulkImport
    {
        $this->validateFile($file);

        // Store the file
        $storedPath = $this->storeFile($file);
        $fileName = $file->getClientOriginalName();

        // Detect entity type if not provided
        if ($entityType === null) {
            $entityType = $this->detectEntityType($storedPath);
        }

        return $this->initiateImport($user, $storedPath, $fileName, $entityType);
    }

    /**
     * Validate the uploaded file.
     *
     * @throws \InvalidArgumentException
     */
    public function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('File size exceeds the maximum allowed size of 10MB.');
        }

        // Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $this->allowedExtensions)) {
            throw new \InvalidArgumentException('Invalid file extension. Allowed: '.implode(', ', $this->allowedExtensions));
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (! in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('Invalid file type. Only CSV and XLSX files are accepted.');
        }
    }

    /**
     * Store the uploaded file.
     */
    protected function storeFile(UploadedFile $file): string
    {
        $fileName = Str::uuid().'_'.$file->getClientOriginalName();

        return $file->storeAs('imports', $fileName, 'local');
    }

    /**
     * Initiate an import from an already-stored file.
     */
    public function initiateImport(
        User $user,
        string $storedPath,
        string $fileName,
        BulkImportEntityType $entityType
    ): BulkImport {
        // Count rows to determine processing mode
        $rowCount = $this->countRows($storedPath);

        // Create the BulkImport record
        $bulkImport = BulkImport::create([
            'user_id' => $user->id,
            'entity_type' => $entityType,
            'file_name' => $fileName,
            'file_path' => $storedPath,
            'status' => BulkImportStatus::Pending,
            'total_rows' => $rowCount,
            'processed_rows' => 0,
            'success_count' => 0,
            'failure_count' => 0,
        ]);

        // Decide sync vs async processing
        if ($rowCount >= $this->asyncThreshold) {
            // Dispatch job for async processing
            ProcessBulkImportJob::dispatch($bulkImport);
        } else {
            // Process synchronously
            $this->processSynchronously($bulkImport);
        }

        return $bulkImport;
    }

    /**
     * Count the number of data rows in the file (excluding header).
     */
    public function countRows(string $storedPath): int
    {
        $fullPath = Storage::disk('local')->path($storedPath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->countCsvRows($fullPath);
        }

        return $this->countExcelRows($fullPath);
    }

    /**
     * Count rows in a CSV file.
     */
    protected function countCsvRows(string $fullPath): int
    {
        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            return 0;
        }

        $count = 0;

        // Skip header row
        fgetcsv($handle);

        while (fgetcsv($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return $count;
    }

    /**
     * Count rows in an Excel file.
     */
    protected function countExcelRows(string $fullPath): int
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Subtract 1 for header row
        return max(0, $worksheet->getHighestRow() - 1);
    }

    /**
     * Detect entity type from file columns.
     */
    public function detectEntityType(string $storedPath): BulkImportEntityType
    {
        $fullPath = Storage::disk('local')->path($storedPath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        $headers = $extension === 'csv'
            ? $this->getCsvHeaders($fullPath)
            : $this->getExcelHeaders($fullPath);

        // Normalize headers
        $headers = array_map(function ($header) {
            return strtolower(trim(str_replace(' ', '_', (string) $header)));
        }, $headers);

        return $this->detectEntityTypeFromHeaders($headers);
    }

    /**
     * Get headers from a CSV file.
     *
     * @return array<string>
     */
    protected function getCsvHeaders(string $fullPath): array
    {
        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle) ?: [];
        fclose($handle);

        return $headers;
    }

    /**
     * Get headers from an Excel file.
     *
     * @return array<string>
     */
    protected function getExcelHeaders(string $fullPath): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestColumn = $worksheet->getHighestColumn();

        $headerRow = $worksheet->rangeToArray('A1:'.$highestColumn.'1', null, true, true, true)[1];

        return array_values($headerRow);
    }

    /**
     * Detect entity type from column headers.
     *
     * @param  array<string>  $headers
     */
    protected function detectEntityTypeFromHeaders(array $headers): BulkImportEntityType
    {
        // Check for port-specific columns
        if (in_array('label', $headers) && in_array('type', $headers) && in_array('subtype', $headers) && in_array('device_name', $headers)) {
            return BulkImportEntityType::Port;
        }

        // Check for device-specific columns
        if (in_array('device_type_name', $headers) && in_array('lifecycle_status', $headers)) {
            return BulkImportEntityType::Device;
        }

        // Check for rack-specific columns
        if (in_array('row_name', $headers) && in_array('u_height', $headers) && in_array('status', $headers) && ! in_array('device_type_name', $headers)) {
            return BulkImportEntityType::Rack;
        }

        // Check for row-specific columns
        if (in_array('room_name', $headers) && in_array('orientation', $headers) && in_array('status', $headers)) {
            return BulkImportEntityType::Row;
        }

        // Check for room-specific columns
        if (in_array('datacenter_name', $headers) && in_array('name', $headers) && in_array('type', $headers) && ! in_array('subtype', $headers)) {
            return BulkImportEntityType::Room;
        }

        // Check for datacenter-specific columns
        if (in_array('name', $headers) && in_array('address_line_1', $headers) && in_array('city', $headers) && in_array('primary_contact_name', $headers)) {
            return BulkImportEntityType::Datacenter;
        }

        // Default to mixed for files with multiple entity types
        return BulkImportEntityType::Mixed;
    }

    /**
     * Process the import synchronously (for small files).
     */
    protected function processSynchronously(BulkImport $bulkImport): void
    {
        $job = new ProcessBulkImportJob($bulkImport);
        $job->handle();
    }

    /**
     * Get the async processing threshold.
     */
    public function getAsyncThreshold(): int
    {
        return $this->asyncThreshold;
    }
}

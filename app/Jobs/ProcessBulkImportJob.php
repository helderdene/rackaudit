<?php

namespace App\Jobs;

use App\Enums\BulkImportEntityType;
use App\Enums\BulkImportStatus;
use App\Imports\CombinedImport;
use App\Imports\DatacenterImport;
use App\Imports\DeviceImport;
use App\Imports\PortImport;
use App\Imports\RackImport;
use App\Imports\RoomImport;
use App\Imports\RowImport;
use App\Models\BulkImport;
use App\Services\ImportErrorReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job for processing bulk import files asynchronously.
 *
 * Processes import files in chunks, updating progress after each chunk,
 * and generates error reports when failures occur.
 */
class ProcessBulkImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of rows to process per chunk.
     */
    protected int $chunkSize = 100;

    /**
     * Create a new job instance.
     */
    public function __construct(public BulkImport $bulkImport) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->markAsProcessing();

        try {
            $this->processImport();
        } catch (\Exception $e) {
            $this->markAsFailed($e);

            throw $e;
        }
    }

    /**
     * Mark the import as processing.
     */
    protected function markAsProcessing(): void
    {
        $this->bulkImport->update([
            'status' => BulkImportStatus::Processing,
            'started_at' => now(),
        ]);
    }

    /**
     * Process the import file.
     */
    protected function processImport(): void
    {
        $filePath = $this->bulkImport->file_path;

        if (! Storage::disk('local')->exists($filePath)) {
            throw new \RuntimeException("Import file not found: {$filePath}");
        }

        $fullPath = Storage::disk('local')->path($filePath);
        $rows = $this->readFileRows($fullPath);

        $totalRows = count($rows);
        $this->bulkImport->update(['total_rows' => $totalRows]);

        $importer = $this->getImporter();
        $allErrors = [];
        $successCount = 0;
        $failureCount = 0;
        $processedRows = 0;

        // Process rows in chunks
        $chunks = array_chunk($rows, $this->chunkSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            DB::beginTransaction();

            try {
                foreach ($chunk as $index => $row) {
                    $globalRowIndex = ($chunkIndex * $this->chunkSize) + $index;
                    $rowNumber = $globalRowIndex + 2; // +2 for header row and 1-based indexing

                    $result = $this->processRow($importer, $row, $rowNumber);

                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failureCount++;
                        $allErrors = array_merge($allErrors, $result['errors']);
                    }

                    $processedRows++;
                }

                DB::commit();

                // Update progress after each chunk
                $this->updateProgress($processedRows, $successCount, $failureCount);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Bulk import chunk failed', [
                    'bulk_import_id' => $this->bulkImport->id,
                    'chunk_index' => $chunkIndex,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        // Generate error report if there are failures
        if (! empty($allErrors)) {
            $errorReportService = app(ImportErrorReportService::class);
            $errorReportService->generateReport($this->bulkImport, $allErrors);
        }

        $this->markAsCompleted($successCount, $failureCount);
    }

    /**
     * Read all rows from the file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function readFileRows(string $fullPath): array
    {
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->readCsvFile($fullPath);
        }

        return $this->readExcelFile($fullPath);
    }

    /**
     * Read CSV file and return rows as arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function readCsvFile(string $fullPath): array
    {
        $rows = [];
        $handle = fopen($fullPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$fullPath}");
        }

        // Read header row
        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return [];
        }

        // Normalize headers to snake_case
        $headers = array_map(function ($header) {
            return strtolower(trim(str_replace(' ', '_', (string) $header)));
        }, $headers);

        // Read data rows
        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Read Excel file and return rows as arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function readExcelFile(string $fullPath): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = [];

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        // Read header row
        $headers = [];
        $headerRow = $worksheet->rangeToArray('A1:'.$highestColumn.'1', null, true, true, true)[1];
        foreach ($headerRow as $cell) {
            $headers[] = strtolower(trim(str_replace(' ', '_', (string) $cell)));
        }

        // Read data rows
        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $rowData = $worksheet->rangeToArray("A{$rowIndex}:".$highestColumn.$rowIndex, null, true, true, true)[$rowIndex];
            $row = [];

            $colIndex = 0;
            foreach ($rowData as $cell) {
                if (isset($headers[$colIndex])) {
                    $row[$headers[$colIndex]] = $cell;
                }
                $colIndex++;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Get the appropriate importer based on entity type.
     */
    protected function getImporter(): DatacenterImport|RoomImport|RowImport|RackImport|DeviceImport|PortImport|CombinedImport
    {
        return match ($this->bulkImport->entity_type) {
            BulkImportEntityType::Datacenter => new DatacenterImport,
            BulkImportEntityType::Room => new RoomImport,
            BulkImportEntityType::Row => new RowImport,
            BulkImportEntityType::Rack => new RackImport,
            BulkImportEntityType::Device => new DeviceImport,
            BulkImportEntityType::Port => new PortImport,
            BulkImportEntityType::Mixed => new CombinedImport,
        };
    }

    /**
     * Process a single row with the importer.
     *
     * @param  array<string, mixed>  $row
     * @return array{success: bool, entity: mixed, errors: array<int, array{row_number: int, field_name: string, error_message: string}>}
     */
    protected function processRow(
        DatacenterImport|RoomImport|RowImport|RackImport|DeviceImport|PortImport|CombinedImport $importer,
        array $row,
        int $rowNumber
    ): array {
        if ($importer instanceof CombinedImport) {
            // CombinedImport needs special handling through its collection method
            // For now, we use a direct approach
            $collection = collect([$row]);
            $importer->collection($collection);

            $errors = $importer->getErrors();
            $lastErrorRowNumber = ! empty($errors) ? $errors[count($errors) - 1]['row_number'] : 0;

            if ($lastErrorRowNumber === 2) { // The collection method uses index + 2
                return [
                    'success' => false,
                    'entity' => null,
                    'errors' => array_map(function ($error) use ($rowNumber) {
                        $error['row_number'] = $rowNumber;

                        return $error;
                    }, $errors),
                ];
            }

            return [
                'success' => true,
                'entity' => null,
                'errors' => [],
            ];
        }

        return $importer->processRow($row, $rowNumber);
    }

    /**
     * Update the progress of the import.
     */
    protected function updateProgress(int $processedRows, int $successCount, int $failureCount): void
    {
        $this->bulkImport->update([
            'processed_rows' => $processedRows,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);
    }

    /**
     * Mark the import as completed.
     */
    protected function markAsCompleted(int $successCount, int $failureCount): void
    {
        $this->bulkImport->update([
            'status' => BulkImportStatus::Completed,
            'processed_rows' => $this->bulkImport->total_rows,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the import as failed.
     */
    protected function markAsFailed(\Exception $e): void
    {
        Log::error('Bulk import job failed', [
            'bulk_import_id' => $this->bulkImport->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->bulkImport->update([
            'status' => BulkImportStatus::Failed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk import job completely failed', [
            'bulk_import_id' => $this->bulkImport->id,
            'error' => $exception->getMessage(),
        ]);

        $this->bulkImport->update([
            'status' => BulkImportStatus::Failed,
            'completed_at' => now(),
        ]);
    }
}

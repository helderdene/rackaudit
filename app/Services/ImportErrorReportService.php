<?php

namespace App\Services;

use App\Models\BulkImport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service for generating and managing import error reports.
 *
 * Generates CSV error reports with row_number, field_name, and error_message
 * columns for failed import rows.
 */
class ImportErrorReportService
{
    /**
     * The storage directory for error reports.
     */
    protected string $storageDirectory = 'import-errors';

    /**
     * Generate an error report CSV for the given errors.
     *
     * @param  array<int, array{row_number: int, field_name: string, error_message: string}>  $errors
     */
    public function generateReport(BulkImport $bulkImport, array $errors): string
    {
        if (empty($errors)) {
            return '';
        }

        $fileName = $this->generateFileName($bulkImport);
        $filePath = $this->storageDirectory.'/'.$fileName;

        $csvContent = $this->buildCsvContent($errors);

        Storage::disk('local')->put($filePath, $csvContent);

        // Update the BulkImport with the error report path
        $bulkImport->update([
            'error_report_path' => $filePath,
        ]);

        return $filePath;
    }

    /**
     * Generate a unique filename for the error report.
     */
    protected function generateFileName(BulkImport $bulkImport): string
    {
        $timestamp = now()->format('Y-m-d_His');
        $uniqueId = Str::random(8);

        return "bulk_import_{$bulkImport->id}_{$timestamp}_{$uniqueId}_errors.csv";
    }

    /**
     * Build the CSV content from errors array.
     *
     * @param  array<int, array{row_number: int, field_name: string, error_message: string}>  $errors
     */
    protected function buildCsvContent(array $errors): string
    {
        $lines = [];

        // Header row
        $lines[] = 'row_number,field_name,error_message';

        // Data rows
        foreach ($errors as $error) {
            $rowNumber = $error['row_number'];
            $fieldName = $this->escapeCsvField($error['field_name']);
            $errorMessage = $this->escapeCsvField($error['error_message']);

            $lines[] = "{$rowNumber},{$fieldName},{$errorMessage}";
        }

        return implode("\n", $lines);
    }

    /**
     * Escape a field value for CSV output.
     */
    protected function escapeCsvField(string $value): string
    {
        // If the value contains comma, quote, or newline, wrap in quotes and escape quotes
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            $value = '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    /**
     * Delete an error report file.
     */
    public function deleteReport(string $filePath): bool
    {
        return Storage::disk('local')->delete($filePath);
    }

    /**
     * Get error reports older than the specified hours.
     *
     * @return array<string>
     */
    public function getExpiredReports(int $hoursOld = 24): array
    {
        $expiredFiles = [];
        $cutoffTime = now()->subHours($hoursOld)->timestamp;

        $files = Storage::disk('local')->files($this->storageDirectory);

        foreach ($files as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            if ($lastModified < $cutoffTime) {
                $expiredFiles[] = $file;
            }
        }

        return $expiredFiles;
    }

    /**
     * Delete all expired error reports and update related BulkImport records.
     *
     * @return int Number of reports deleted
     */
    public function cleanupExpiredReports(int $hoursOld = 24): int
    {
        $expiredFiles = $this->getExpiredReports($hoursOld);
        $deletedCount = 0;

        foreach ($expiredFiles as $filePath) {
            // Clear the error_report_path on any BulkImport records using this file
            BulkImport::query()
                ->where('error_report_path', $filePath)
                ->update(['error_report_path' => null]);

            // Delete the file
            if ($this->deleteReport($filePath)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}

<?php

namespace App\Actions\ExpectedConnections;

use App\Enums\CableType;
use App\Enums\ExpectedConnectionStatus;
use App\Imports\ConnectionFileImport;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Services\FuzzyMatchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Action to parse an implementation file and create expected connections.
 *
 * Orchestrates the parsing process:
 * 1. Validates the file type and size
 * 2. Parses the file using ConnectionFileImport
 * 3. Matches devices/ports using FuzzyMatchingService
 * 4. Creates ExpectedConnection records in pending_review status
 * 5. Handles version replacement (soft deletes previous version's connections)
 */
class ParseConnectionsAction
{
    /**
     * Maximum file size for synchronous processing (5 MB).
     */
    public const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024;

    /**
     * Allowed MIME types for parsing.
     *
     * @var array<string>
     */
    protected const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-excel', // xls
        'text/csv', // csv
    ];

    /**
     * The fuzzy matching service.
     */
    protected FuzzyMatchingService $fuzzyMatcher;

    /**
     * Create a new action instance.
     */
    public function __construct(?FuzzyMatchingService $fuzzyMatcher = null)
    {
        $this->fuzzyMatcher = $fuzzyMatcher ?? new FuzzyMatchingService;
    }

    /**
     * Execute the parsing action.
     *
     * @return array{
     *   success: bool,
     *   error?: string,
     *   connections?: array<int, array>,
     *   statistics?: array{total: int, exact: int, suggested: int, unrecognized: int},
     *   row_errors?: array<int, array{row_number: int, field: string, message: string}>,
     *   parse_errors?: array<string>
     * }
     */
    public function execute(ImplementationFile $implementationFile): array
    {
        // Validate file type
        if (! $this->isValidFileType($implementationFile)) {
            return [
                'success' => false,
                'error' => 'File type not supported for parsing. Only Excel (xlsx, xls) and CSV files can be parsed.',
            ];
        }

        // Validate file size
        if ($implementationFile->file_size > self::MAX_FILE_SIZE_BYTES) {
            $maxMb = self::MAX_FILE_SIZE_BYTES / (1024 * 1024);

            return [
                'success' => false,
                'error' => "File size exceeds the maximum allowed size of {$maxMb} MB for synchronous processing.",
            ];
        }

        // Validate file is approved
        if (! $implementationFile->isApproved()) {
            return [
                'success' => false,
                'error' => 'Implementation file must be approved before parsing.',
            ];
        }

        // Get the file path
        $filePath = $this->getFilePath($implementationFile);
        if (! $filePath) {
            return [
                'success' => false,
                'error' => 'Could not locate the file for parsing.',
            ];
        }

        // Determine file type
        $fileType = $this->getFileType($implementationFile);

        // Parse the file
        $import = new ConnectionFileImport;
        $import->loadFile($filePath, $fileType);
        $parseResult = $import->parse();

        if (! $parseResult['success']) {
            return [
                'success' => false,
                'error' => 'Failed to parse file.',
                'parse_errors' => $parseResult['errors'],
            ];
        }

        // If no rows were parsed, return error
        if (empty($parseResult['rows'])) {
            return [
                'success' => false,
                'error' => 'No valid connection data found in the file.',
                'parse_errors' => $parseResult['errors'],
            ];
        }

        // Match devices and ports for each row
        $matchedConnections = [];
        foreach ($parseResult['rows'] as $row) {
            $match = $this->fuzzyMatcher->matchConnection($row);
            $matchedConnections[] = array_merge($row, ['match' => $match]);
        }

        // Calculate statistics
        $statistics = $this->fuzzyMatcher->getMatchStatistics(
            array_map(fn ($conn) => $conn['match'], $matchedConnections)
        );

        // Create expected connections in a transaction
        $createdConnections = $this->createExpectedConnections(
            $implementationFile,
            $matchedConnections
        );

        return [
            'success' => true,
            'connections' => $createdConnections,
            'statistics' => $statistics,
            'row_errors' => $parseResult['row_errors'],
            'parse_errors' => $parseResult['errors'],
        ];
    }

    /**
     * Check if the file type is valid for parsing.
     */
    protected function isValidFileType(ImplementationFile $implementationFile): bool
    {
        return in_array($implementationFile->mime_type, self::ALLOWED_MIME_TYPES, true);
    }

    /**
     * Get the file path from storage.
     */
    protected function getFilePath(ImplementationFile $implementationFile): ?string
    {
        $filePath = $implementationFile->file_path;

        // Try local storage first
        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->path($filePath);
        }

        // Try public storage
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->path($filePath);
        }

        return null;
    }

    /**
     * Determine file type from MIME type.
     */
    protected function getFileType(ImplementationFile $implementationFile): string
    {
        return match ($implementationFile->mime_type) {
            'text/csv' => 'csv',
            'application/vnd.ms-excel' => 'xls',
            default => 'xlsx',
        };
    }

    /**
     * Create expected connection records.
     *
     * @param  array<int, array>  $matchedConnections
     * @return array<int, array>
     */
    protected function createExpectedConnections(
        ImplementationFile $implementationFile,
        array $matchedConnections
    ): array {
        return DB::transaction(function () use ($implementationFile, $matchedConnections) {
            // Archive previous version's expected connections if this is a new version
            $this->archivePreviousVersionConnections($implementationFile);

            $createdConnections = [];

            foreach ($matchedConnections as $connection) {
                $match = $connection['match'];

                // Parse cable type
                $cableType = $this->parseCableType($connection['cable_type'] ?? null);

                // Parse cable length
                $cableLength = $this->parseCableLength($connection['cable_length'] ?? null);

                $expectedConnection = ExpectedConnection::create([
                    'implementation_file_id' => $implementationFile->id,
                    'source_device_id' => $match['source_device']['device_id'],
                    'source_port_id' => $match['source_port']['port_id'],
                    'dest_device_id' => $match['dest_device']['device_id'],
                    'dest_port_id' => $match['dest_port']['port_id'],
                    'cable_type' => $cableType,
                    'cable_length' => $cableLength,
                    'row_number' => $connection['row_number'],
                    'status' => ExpectedConnectionStatus::PendingReview,
                ]);

                $createdConnections[] = [
                    'id' => $expectedConnection->id,
                    'row_number' => $connection['row_number'],
                    'source_device' => [
                        'id' => $match['source_device']['device_id'],
                        'original' => $match['source_device']['original'],
                        'matched_name' => $match['source_device']['matched_name'],
                        'confidence' => $match['source_device']['confidence'],
                        'match_type' => $match['source_device']['match_type'],
                    ],
                    'source_port' => [
                        'id' => $match['source_port']['port_id'],
                        'original' => $match['source_port']['original'],
                        'matched_label' => $match['source_port']['matched_label'],
                        'confidence' => $match['source_port']['confidence'],
                        'match_type' => $match['source_port']['match_type'],
                    ],
                    'dest_device' => [
                        'id' => $match['dest_device']['device_id'],
                        'original' => $match['dest_device']['original'],
                        'matched_name' => $match['dest_device']['matched_name'],
                        'confidence' => $match['dest_device']['confidence'],
                        'match_type' => $match['dest_device']['match_type'],
                    ],
                    'dest_port' => [
                        'id' => $match['dest_port']['port_id'],
                        'original' => $match['dest_port']['original'],
                        'matched_label' => $match['dest_port']['matched_label'],
                        'confidence' => $match['dest_port']['confidence'],
                        'match_type' => $match['dest_port']['match_type'],
                    ],
                    'cable_type' => $cableType?->value,
                    'cable_length' => $cableLength,
                    'overall_match_type' => $match['overall_match_type'],
                    'status' => ExpectedConnectionStatus::PendingReview->value,
                ];
            }

            return $createdConnections;
        });
    }

    /**
     * Archive expected connections from previous versions.
     */
    protected function archivePreviousVersionConnections(ImplementationFile $implementationFile): void
    {
        // If this file has a version group, soft delete connections from other versions
        if ($implementationFile->version_group_id) {
            $previousVersionIds = ImplementationFile::query()
                ->where('version_group_id', $implementationFile->version_group_id)
                ->where('id', '!=', $implementationFile->id)
                ->pluck('id');

            if ($previousVersionIds->isNotEmpty()) {
                ExpectedConnection::query()
                    ->whereIn('implementation_file_id', $previousVersionIds)
                    ->delete(); // Soft delete
            }
        }
    }

    /**
     * Parse cable type from string value.
     */
    protected function parseCableType(?string $value): ?CableType
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);

        // Try to match by label
        foreach (CableType::cases() as $case) {
            if (strcasecmp($case->label(), $value) === 0) {
                return $case;
            }
            if (strcasecmp($case->value, $value) === 0) {
                return $case;
            }
            if (strcasecmp($case->name, $value) === 0) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Parse cable length from string value.
     */
    protected function parseCableLength(?string $value): ?float
    {
        if (empty($value)) {
            return null;
        }

        // Remove any non-numeric characters except decimal point
        $value = preg_replace('/[^0-9.]/', '', $value);

        if (empty($value)) {
            return null;
        }

        $length = (float) $value;

        return $length > 0 ? $length : null;
    }
}

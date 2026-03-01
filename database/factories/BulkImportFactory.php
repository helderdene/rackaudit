<?php

namespace Database\Factories;

use App\Enums\BulkImportEntityType;
use App\Enums\BulkImportStatus;
use App\Models\BulkImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating BulkImport test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BulkImport>
 */
class BulkImportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = BulkImport::class;

    /**
     * Realistic file names for datacenter imports.
     *
     * @var array<string>
     */
    private array $fileNames = [
        'devices_inventory.xlsx',
        'datacenter_assets.csv',
        'rack_placement.xlsx',
        'server_list.csv',
        'network_devices.xlsx',
        'infrastructure_data.xlsx',
        'asset_import.csv',
        'quarterly_inventory.xlsx',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entityType = fake()->randomElement(BulkImportEntityType::cases());
        $fileName = fake()->randomElement($this->fileNames);
        $totalRows = fake()->numberBetween(10, 500);
        $processedRows = fake()->numberBetween(0, $totalRows);
        $successCount = fake()->numberBetween(0, $processedRows);
        $failureCount = $processedRows - $successCount;

        return [
            'user_id' => User::factory(),
            'entity_type' => $entityType,
            'file_name' => $fileName,
            'file_path' => 'imports/' . fake()->uuid() . '_' . $fileName,
            'status' => BulkImportStatus::Pending,
            'total_rows' => $totalRows,
            'processed_rows' => $processedRows,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'error_report_path' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Set import status to pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BulkImportStatus::Pending,
            'processed_rows' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'error_report_path' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Set import status to processing.
     */
    public function processing(): static
    {
        return $this->state(function (array $attributes) {
            $totalRows = $attributes['total_rows'] ?? 100;
            $processedRows = fake()->numberBetween(1, $totalRows - 1);
            $successCount = fake()->numberBetween(0, $processedRows);

            return [
                'status' => BulkImportStatus::Processing,
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
                'success_count' => $successCount,
                'failure_count' => $processedRows - $successCount,
                'started_at' => now()->subMinutes(fake()->numberBetween(1, 10)),
                'completed_at' => null,
            ];
        });
    }

    /**
     * Set import status to completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalRows = $attributes['total_rows'] ?? 100;
            $successCount = fake()->numberBetween((int) ($totalRows * 0.8), $totalRows);
            $failureCount = $totalRows - $successCount;

            return [
                'status' => BulkImportStatus::Completed,
                'total_rows' => $totalRows,
                'processed_rows' => $totalRows,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'error_report_path' => $failureCount > 0
                    ? 'import-errors/bulk_import_' . fake()->uuid() . '_errors.csv'
                    : null,
                'started_at' => now()->subMinutes(fake()->numberBetween(5, 30)),
                'completed_at' => now()->subMinutes(fake()->numberBetween(1, 4)),
            ];
        });
    }

    /**
     * Set import status to failed.
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $totalRows = $attributes['total_rows'] ?? 100;
            $processedRows = fake()->numberBetween(0, $totalRows);
            $failureCount = fake()->numberBetween((int) ($processedRows * 0.5), $processedRows);
            $successCount = $processedRows - $failureCount;

            return [
                'status' => BulkImportStatus::Failed,
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'error_report_path' => 'import-errors/bulk_import_' . fake()->uuid() . '_errors.csv',
                'started_at' => now()->subMinutes(fake()->numberBetween(5, 30)),
                'completed_at' => now()->subMinutes(fake()->numberBetween(1, 4)),
            ];
        });
    }

    /**
     * Set a specific entity type for the import.
     */
    public function forEntityType(BulkImportEntityType $entityType): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => $entityType,
        ]);
    }

    /**
     * Set specific row counts for the import.
     */
    public function withRowCounts(int $total, int $processed = 0, int $success = 0, int $failure = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'total_rows' => $total,
            'processed_rows' => $processed,
            'success_count' => $success,
            'failure_count' => $failure,
        ]);
    }
}

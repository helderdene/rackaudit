<?php

namespace Database\Factories;

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Models\BulkExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating BulkExport test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BulkExport>
 */
class BulkExportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = BulkExport::class;

    /**
     * Realistic file names for datacenter exports.
     *
     * @var array<string>
     */
    private array $fileNames = [
        'devices_export.xlsx',
        'datacenter_backup.csv',
        'rack_inventory.xlsx',
        'server_list_export.csv',
        'network_devices_export.xlsx',
        'infrastructure_backup.xlsx',
        'asset_export.csv',
        'quarterly_report.xlsx',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entityType = fake()->randomElement([
            BulkImportEntityType::Datacenter,
            BulkImportEntityType::Room,
            BulkImportEntityType::Row,
            BulkImportEntityType::Rack,
            BulkImportEntityType::Device,
            BulkImportEntityType::Port,
        ]);
        $format = fake()->randomElement(['csv', 'xlsx']);
        $fileName = fake()->randomElement($this->fileNames);
        $totalRows = fake()->numberBetween(10, 500);
        $processedRows = fake()->numberBetween(0, $totalRows);

        return [
            'user_id' => User::factory(),
            'entity_type' => $entityType,
            'format' => $format,
            'file_name' => $fileName,
            'file_path' => 'exports/'.fake()->uuid().'_'.$fileName,
            'status' => BulkExportStatus::Pending,
            'total_rows' => $totalRows,
            'processed_rows' => $processedRows,
            'filters' => [],
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Set export status to pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BulkExportStatus::Pending,
            'processed_rows' => 0,
            'file_name' => null,
            'file_path' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Set export status to processing.
     */
    public function processing(): static
    {
        return $this->state(function (array $attributes) {
            $totalRows = $attributes['total_rows'] ?? 100;
            $processedRows = fake()->numberBetween(1, $totalRows - 1);

            return [
                'status' => BulkExportStatus::Processing,
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
                'started_at' => now()->subMinutes(fake()->numberBetween(1, 10)),
                'completed_at' => null,
            ];
        });
    }

    /**
     * Set export status to completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalRows = $attributes['total_rows'] ?? 100;
            $format = $attributes['format'] ?? 'xlsx';
            $fileName = 'export_'.fake()->uuid().'.'.$format;

            return [
                'status' => BulkExportStatus::Completed,
                'total_rows' => $totalRows,
                'processed_rows' => $totalRows,
                'file_name' => $fileName,
                'file_path' => 'exports/'.$fileName,
                'started_at' => now()->subMinutes(fake()->numberBetween(5, 30)),
                'completed_at' => now()->subMinutes(fake()->numberBetween(1, 4)),
            ];
        });
    }

    /**
     * Set export status to failed.
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $totalRows = $attributes['total_rows'] ?? 100;
            $processedRows = fake()->numberBetween(0, $totalRows);

            return [
                'status' => BulkExportStatus::Failed,
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
                'file_name' => null,
                'file_path' => null,
                'started_at' => now()->subMinutes(fake()->numberBetween(5, 30)),
                'completed_at' => now()->subMinutes(fake()->numberBetween(1, 4)),
            ];
        });
    }

    /**
     * Set a specific entity type for the export.
     */
    public function forEntityType(BulkImportEntityType $entityType): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => $entityType,
        ]);
    }

    /**
     * Set the export format to CSV.
     */
    public function csv(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'csv',
        ]);
    }

    /**
     * Set the export format to XLSX.
     */
    public function xlsx(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'xlsx',
        ]);
    }

    /**
     * Set specific filters for the export.
     *
     * @param  array<string, int|null>  $filters
     */
    public function withFilters(array $filters): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => $filters,
        ]);
    }

    /**
     * Set specific row counts for the export.
     */
    public function withRowCounts(int $total, int $processed = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'total_rows' => $total,
            'processed_rows' => $processed,
        ]);
    }
}

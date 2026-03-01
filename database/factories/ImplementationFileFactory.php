<?php

namespace Database\Factories;

use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for generating ImplementationFile test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImplementationFile>
 */
class ImplementationFileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ImplementationFile::class;

    /**
     * Supported file types with their MIME types and extensions.
     *
     * @var array<string, array{mime: string, extension: string}>
     */
    protected array $fileTypes = [
        'pdf' => ['mime' => 'application/pdf', 'extension' => 'pdf'],
        'xlsx' => ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'extension' => 'xlsx'],
        'xls' => ['mime' => 'application/vnd.ms-excel', 'extension' => 'xls'],
        'csv' => ['mime' => 'text/csv', 'extension' => 'csv'],
        'docx' => ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension' => 'docx'],
        'txt' => ['mime' => 'text/plain', 'extension' => 'txt'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileType = fake()->randomElement(array_keys($this->fileTypes));
        $typeInfo = $this->fileTypes[$fileType];
        $uuid = Str::uuid()->toString();
        $fileName = $uuid.'.'.$typeInfo['extension'];
        $originalName = $this->generateFileName($typeInfo['extension']);

        return [
            'datacenter_id' => Datacenter::factory(),
            'file_name' => $fileName,
            'original_name' => $originalName,
            'description' => fake()->optional(0.7)->sentence(10),
            'file_path' => fn (array $attributes) => 'implementation-files/'.$attributes['datacenter_id'].'/'.$fileName,
            'file_size' => fake()->numberBetween(1024, 10485760), // 1 KB to 10 MB
            'mime_type' => $typeInfo['mime'],
            'uploaded_by' => User::factory(),
            'version_group_id' => null,
            'version_number' => 1,
            'approval_status' => 'pending_approval',
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    /**
     * Generate a realistic file name based on extension.
     */
    protected function generateFileName(string $extension): string
    {
        $prefixes = [
            'implementation_spec',
            'network_diagram',
            'rack_layout',
            'connection_matrix',
            'datacenter_design',
            'infrastructure_plan',
            'cabling_standard',
            'equipment_list',
            'power_distribution',
            'cooling_layout',
        ];

        $prefix = fake()->randomElement($prefixes);
        $version = fake()->optional(0.5)->randomElement(['_v1', '_v2', '_v3', '_final', '_draft']);

        return $prefix.($version ?? '').'.'.$extension;
    }

    /**
     * Indicate that the file is a PDF document.
     */
    public function pdf(): static
    {
        $uuid = Str::uuid()->toString();
        $fileName = $uuid.'.pdf';
        $originalName = $this->generateFileName('pdf');

        return $this->state(fn (array $attributes) => [
            'file_name' => $fileName,
            'original_name' => $originalName,
            'file_path' => fn (array $attrs) => 'implementation-files/'.$attrs['datacenter_id'].'/'.$fileName,
            'mime_type' => 'application/pdf',
        ]);
    }

    /**
     * Indicate that the file is an Excel XLSX spreadsheet.
     */
    public function xlsx(): static
    {
        $uuid = Str::uuid()->toString();
        $fileName = $uuid.'.xlsx';
        $originalName = $this->generateFileName('xlsx');

        return $this->state(fn (array $attributes) => [
            'file_name' => $fileName,
            'original_name' => $originalName,
            'file_path' => fn (array $attrs) => 'implementation-files/'.$attrs['datacenter_id'].'/'.$fileName,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Indicate that the file is an Excel XLS spreadsheet.
     */
    public function xls(): static
    {
        $uuid = Str::uuid()->toString();
        $fileName = $uuid.'.xls';
        $originalName = $this->generateFileName('xls');

        return $this->state(fn (array $attributes) => [
            'file_name' => $fileName,
            'original_name' => $originalName,
            'file_path' => fn (array $attrs) => 'implementation-files/'.$attrs['datacenter_id'].'/'.$fileName,
            'mime_type' => 'application/vnd.ms-excel',
        ]);
    }

    /**
     * Indicate that the file is a CSV file.
     */
    public function csv(): static
    {
        $uuid = Str::uuid()->toString();
        $fileName = $uuid.'.csv';
        $originalName = $this->generateFileName('csv');

        return $this->state(fn (array $attributes) => [
            'file_name' => $fileName,
            'original_name' => $originalName,
            'file_path' => fn (array $attrs) => 'implementation-files/'.$attrs['datacenter_id'].'/'.$fileName,
            'mime_type' => 'text/csv',
        ]);
    }

    /**
     * Indicate that the file is a Word DOCX document.
     */
    public function docx(): static
    {
        $uuid = Str::uuid()->toString();
        $fileName = $uuid.'.docx';
        $originalName = $this->generateFileName('docx');

        return $this->state(fn (array $attributes) => [
            'file_name' => $fileName,
            'original_name' => $originalName,
            'file_path' => fn (array $attrs) => 'implementation-files/'.$attrs['datacenter_id'].'/'.$fileName,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Indicate that the file is a plain text file.
     */
    public function txt(): static
    {
        $uuid = Str::uuid()->toString();
        $fileName = $uuid.'.txt';
        $originalName = $this->generateFileName('txt');

        return $this->state(fn (array $attributes) => [
            'file_name' => $fileName,
            'original_name' => $originalName,
            'file_path' => fn (array $attrs) => 'implementation-files/'.$attrs['datacenter_id'].'/'.$fileName,
            'mime_type' => 'text/plain',
        ]);
    }

    /**
     * Set specific version information for the file.
     */
    public function asVersion(int $versionNumber, ?int $versionGroupId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'version_number' => $versionNumber,
            'version_group_id' => $versionGroupId,
        ]);
    }

    /**
     * Create a file as the first version (version_group_id will be set to its own id after creation).
     *
     * Note: After using this state, you should call:
     * $file->update(['version_group_id' => $file->id])
     */
    public function asFirstVersion(): static
    {
        return $this->state(fn (array $attributes) => [
            'version_number' => 1,
            'version_group_id' => null,
        ]);
    }

    /**
     * Create a version chain with the specified number of versions.
     *
     * Returns a collection of ImplementationFile models representing a version chain.
     * The first file's version_group_id will be set to its own id.
     *
     * @param  int  $count  Number of versions to create
     * @return \Illuminate\Database\Eloquent\Collection<int, ImplementationFile>
     */
    public function withVersions(int $count = 3): static
    {
        return $this->afterCreating(function (ImplementationFile $file) use ($count) {
            // Set version_group_id to own id for the first file
            $file->update(['version_group_id' => $file->id]);

            // Create additional versions
            for ($i = 2; $i <= $count; $i++) {
                ImplementationFile::factory()->create([
                    'datacenter_id' => $file->datacenter_id,
                    'original_name' => $file->original_name,
                    'uploaded_by' => $file->uploaded_by,
                    'version_group_id' => $file->id,
                    'version_number' => $i,
                ]);
            }
        });
    }

    /**
     * Indicate that the file has a specific size in bytes.
     */
    public function withSize(int $bytes): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $bytes,
        ]);
    }

    /**
     * Indicate that the file is small (under 100 KB).
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => fake()->numberBetween(1024, 102400), // 1 KB to 100 KB
        ]);
    }

    /**
     * Indicate that the file is large (5-10 MB).
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => fake()->numberBetween(5242880, 10485760), // 5 MB to 10 MB
        ]);
    }

    /**
     * Indicate that the file has a description.
     */
    public function withDescription(?string $description = null): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $description ?? fake()->paragraph(),
        ]);
    }

    /**
     * Indicate that the file has been approved.
     */
    public function approved(?User $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'approved',
            'approved_by' => $approver?->id ?? User::factory(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the file is pending approval (default state).
     */
    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'pending_approval',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }
}

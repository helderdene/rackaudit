<?php

namespace Database\Factories;

use App\Enums\CableType;
use App\Enums\ExpectedConnectionStatus;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating ExpectedConnection test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpectedConnection>
 */
class ExpectedConnectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ExpectedConnection::class;

    /**
     * Define the model's default state.
     *
     * Default generates a pending review expected connection with Ethernet ports.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sourceDevice = Device::factory()->create();
        $destDevice = Device::factory()->create();

        return [
            'implementation_file_id' => ImplementationFile::factory()->xlsx()->approved(),
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id])->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => Port::factory()->ethernet()->create(['device_id' => $destDevice->id])->id,
            'cable_type' => CableType::Cat6,
            'cable_length' => fake()->randomFloat(2, 0.5, 50),
            'row_number' => fake()->numberBetween(1, 100),
            'status' => ExpectedConnectionStatus::PendingReview,
        ];
    }

    /**
     * Create an expected connection in pending review status.
     */
    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpectedConnectionStatus::PendingReview,
        ]);
    }

    /**
     * Create an expected connection in confirmed status.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpectedConnectionStatus::Confirmed,
        ]);
    }

    /**
     * Create an expected connection in skipped status.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpectedConnectionStatus::Skipped,
        ]);
    }

    /**
     * Create an expected connection with a specific implementation file.
     */
    public function forImplementationFile(ImplementationFile $implementationFile): static
    {
        return $this->state(fn (array $attributes) => [
            'implementation_file_id' => $implementationFile->id,
        ]);
    }

    /**
     * Create an expected connection with Cat5e cable.
     */
    public function cat5e(): static
    {
        return $this->state(fn (array $attributes) => [
            'cable_type' => CableType::Cat5e,
        ]);
    }

    /**
     * Create an expected connection with Cat6 cable.
     */
    public function cat6(): static
    {
        return $this->state(fn (array $attributes) => [
            'cable_type' => CableType::Cat6,
        ]);
    }

    /**
     * Create an expected connection with Cat6a cable.
     */
    public function cat6a(): static
    {
        return $this->state(fn (array $attributes) => [
            'cable_type' => CableType::Cat6a,
        ]);
    }

    /**
     * Create a fiber single-mode expected connection.
     */
    public function fiberSm(): static
    {
        return $this->state(function (array $attributes) {
            $sourceDevice = Device::factory()->create();
            $destDevice = Device::factory()->create();

            return [
                'source_device_id' => $sourceDevice->id,
                'source_port_id' => Port::factory()->fiber()->create(['device_id' => $sourceDevice->id])->id,
                'dest_device_id' => $destDevice->id,
                'dest_port_id' => Port::factory()->fiber()->create(['device_id' => $destDevice->id])->id,
                'cable_type' => CableType::FiberSm,
            ];
        });
    }

    /**
     * Create a fiber multi-mode expected connection.
     */
    public function fiberMm(): static
    {
        return $this->state(function (array $attributes) {
            $sourceDevice = Device::factory()->create();
            $destDevice = Device::factory()->create();

            return [
                'source_device_id' => $sourceDevice->id,
                'source_port_id' => Port::factory()->fiber()->create(['device_id' => $sourceDevice->id])->id,
                'dest_device_id' => $destDevice->id,
                'dest_port_id' => Port::factory()->fiber()->create(['device_id' => $destDevice->id])->id,
                'cable_type' => CableType::FiberMm,
            ];
        });
    }

    /**
     * Create an expected connection without cable type (nullable).
     */
    public function withoutCableType(): static
    {
        return $this->state(fn (array $attributes) => [
            'cable_type' => null,
        ]);
    }

    /**
     * Create an expected connection without cable length (nullable).
     */
    public function withoutCableLength(): static
    {
        return $this->state(fn (array $attributes) => [
            'cable_length' => null,
        ]);
    }

    /**
     * Create an expected connection with specific row number.
     */
    public function rowNumber(int $rowNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'row_number' => $rowNumber,
        ]);
    }

    /**
     * Create an unmatched expected connection (device/port IDs are null).
     *
     * Used for testing fuzzy matching scenarios where devices/ports
     * couldn't be matched from parsed data.
     */
    public function unmatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_device_id' => null,
            'source_port_id' => null,
            'dest_device_id' => null,
            'dest_port_id' => null,
        ]);
    }

    /**
     * Create a partially matched expected connection (source matched, dest not).
     */
    public function partiallyMatched(): static
    {
        return $this->state(function (array $attributes) {
            $sourceDevice = Device::factory()->create();

            return [
                'source_device_id' => $sourceDevice->id,
                'source_port_id' => Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id])->id,
                'dest_device_id' => null,
                'dest_port_id' => null,
            ];
        });
    }
}

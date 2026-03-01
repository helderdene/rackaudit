<?php

namespace Database\Factories;

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Rack;
use App\Models\Row;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Rack test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rack>
 */
class RackFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Rack::class;

    /**
     * Rack manufacturers.
     *
     * @var array<string>
     */
    private array $manufacturers = [
        'APC',
        'Eaton',
        'Vertiv',
        'Schneider Electric',
        'Dell',
        'HP',
        'IBM',
        'Rittal',
        'Chatsworth',
        'Panduit',
    ];

    /**
     * Rack models by manufacturer.
     *
     * @var array<string, array<string>>
     */
    private array $models = [
        'APC' => ['NetShelter SX', 'NetShelter CX', 'NetShelter VX'],
        'Eaton' => ['RS Series', 'REV Series', 'RSV Series'],
        'Vertiv' => ['VR Rack', 'DCE Rack', 'Knurr DCM'],
        'Schneider Electric' => ['Uniflair', 'InRow RC'],
        'Dell' => ['PowerEdge Rack', 'PowerEdge 4220'],
        'HP' => ['G2 Rack', 'Advanced Series'],
        'IBM' => ['Enterprise Rack', 'NetBAY S2'],
        'Rittal' => ['TS IT', 'VX IT'],
        'Chatsworth' => ['F-Series', 'E-Series'],
        'Panduit' => ['FlexFusion', 'Net-Access'],
    ];

    /**
     * Rack depth options.
     *
     * @var array<string>
     */
    private array $depths = [
        '600mm',
        '800mm',
        '900mm',
        '1000mm',
        '1070mm',
        '1100mm',
        '1200mm',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $manufacturer = fake()->optional(0.7)->randomElement($this->manufacturers);

        return [
            'name' => 'Rack '.fake()->unique()->randomLetter().fake()->numberBetween(1, 99),
            'position' => fake()->numberBetween(1, 20),
            'u_height' => fake()->randomElement(RackUHeight::cases()),
            'serial_number' => fake()->optional()->bothify('SN-####-????'),
            'status' => RackStatus::Active,
            'row_id' => Row::factory(),
            'manufacturer' => $manufacturer,
            'model' => $manufacturer ? fake()->optional(0.8)->randomElement($this->models[$manufacturer] ?? ['Standard Rack']) : null,
            'depth' => fake()->optional(0.6)->randomElement($this->depths),
            'installation_date' => fake()->optional(0.5)->dateTimeBetween('-5 years', 'now'),
            'location_notes' => fake()->optional(0.3)->sentence(),
            'specs' => $this->generateSpecs(),
        ];
    }

    /**
     * Generate realistic rack specifications.
     *
     * @return array<string, mixed>|null
     */
    private function generateSpecs(): ?array
    {
        if (fake()->boolean(60)) {
            return [
                'max_weight_kg' => fake()->randomElement([500, 750, 1000, 1250, 1500]),
                'cable_management' => fake()->randomElement(['vertical', 'horizontal', 'both']),
                'power_phases' => fake()->randomElement([1, 3]),
                'cooling_type' => fake()->randomElement(['passive', 'rear-door', 'in-row']),
            ];
        }

        return null;
    }

    /**
     * Indicate that the rack is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RackStatus::Active,
        ]);
    }

    /**
     * Indicate that the rack is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RackStatus::Inactive,
        ]);
    }

    /**
     * Indicate that the rack is under maintenance.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RackStatus::Maintenance,
        ]);
    }

    /**
     * Set a specific U-height for the rack.
     */
    public function withUHeight(RackUHeight $uHeight): static
    {
        return $this->state(fn (array $attributes) => [
            'u_height' => $uHeight,
        ]);
    }

    /**
     * Set a specific position for the rack.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }

    /**
     * Create a rack with full specification details.
     */
    public function withFullSpecs(): static
    {
        return $this->state(function (array $attributes) {
            $manufacturer = fake()->randomElement($this->manufacturers);

            return [
                'manufacturer' => $manufacturer,
                'model' => fake()->randomElement($this->models[$manufacturer] ?? ['Standard Rack']),
                'depth' => fake()->randomElement($this->depths),
                'installation_date' => fake()->dateTimeBetween('-5 years', 'now'),
                'location_notes' => fake()->sentence(),
                'specs' => [
                    'max_weight_kg' => fake()->randomElement([500, 750, 1000, 1250, 1500]),
                    'cable_management' => fake()->randomElement(['vertical', 'horizontal', 'both']),
                    'power_phases' => fake()->randomElement([1, 3]),
                    'cooling_type' => fake()->randomElement(['passive', 'rear-door', 'in-row']),
                ],
            ];
        });
    }

    /**
     * Create a rack with no optional enhancement fields.
     */
    public function withoutEnhancements(): static
    {
        return $this->state(fn (array $attributes) => [
            'manufacturer' => null,
            'model' => null,
            'depth' => null,
            'installation_date' => null,
            'location_notes' => null,
            'specs' => null,
        ]);
    }
}

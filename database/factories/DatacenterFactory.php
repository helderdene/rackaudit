<?php

namespace Database\Factories;

use App\Models\Datacenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Datacenter test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Datacenter>
 */
class DatacenterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Datacenter::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Data Center',
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state_province' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'company_name' => null,
            'primary_contact_name' => fake()->name(),
            'primary_contact_email' => fake()->companyEmail(),
            'primary_contact_phone' => fake()->phoneNumber(),
            'secondary_contact_name' => null,
            'secondary_contact_email' => null,
            'secondary_contact_phone' => null,
            'floor_plan_path' => null,
        ];
    }

    /**
     * Indicate that the datacenter has a floor plan uploaded.
     */
    public function withFloorPlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'floor_plan_path' => 'floor-plans/datacenter_'.fake()->unique()->randomNumber(5).'_'.time().'.png',
        ]);
    }

    /**
     * Indicate that the datacenter has secondary contact information.
     */
    public function withSecondaryContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'secondary_contact_name' => fake()->name(),
            'secondary_contact_email' => fake()->companyEmail(),
            'secondary_contact_phone' => fake()->phoneNumber(),
        ]);
    }

    /**
     * Indicate that the datacenter has a company name.
     */
    public function withCompanyName(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_name' => fake()->company(),
        ]);
    }

    /**
     * Indicate that the datacenter has a suite/unit address line 2.
     */
    public function withAddressLine2(): static
    {
        return $this->state(fn (array $attributes) => [
            'address_line_2' => fake()->secondaryAddress(),
        ]);
    }

    /**
     * Indicate that the datacenter has all optional fields filled.
     */
    public function complete(): static
    {
        return $this->withFloorPlan()
            ->withSecondaryContact()
            ->withCompanyName()
            ->withAddressLine2();
    }
}

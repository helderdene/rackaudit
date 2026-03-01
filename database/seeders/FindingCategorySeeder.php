<?php

namespace Database\Seeders;

use App\Enums\DiscrepancyType;
use App\Models\FindingCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds default finding categories from DiscrepancyType enum values.
 *
 * Default categories are marked with is_default=true to distinguish
 * them from user-created custom categories.
 */
class FindingCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default categories based on DiscrepancyType values
        // Excludes 'Matched' as it's not a discrepancy
        $defaultCategories = [
            [
                'name' => DiscrepancyType::Missing->label(),
                'description' => 'Expected item was not found during the audit.',
                'is_default' => true,
            ],
            [
                'name' => DiscrepancyType::Unexpected->label(),
                'description' => 'Item found that was not expected to be present.',
                'is_default' => true,
            ],
            [
                'name' => DiscrepancyType::Mismatched->label(),
                'description' => 'Item found but with different properties than expected.',
                'is_default' => true,
            ],
            [
                'name' => DiscrepancyType::Conflicting->label(),
                'description' => 'Multiple conflicting records found for the same item.',
                'is_default' => true,
            ],
            [
                'name' => DiscrepancyType::ConfigurationMismatch->label(),
                'description' => 'Configuration settings do not match expected values.',
                'is_default' => true,
            ],
        ];

        foreach ($defaultCategories as $category) {
            FindingCategory::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}

<?php

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Enums\ReportType;
use App\Models\Datacenter;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check since Vue components may not exist yet
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: Builder page includes report types with correct structure for ReportTypeSelector.
 *
 * This test verifies that the index action returns report type data in the format
 * expected by the ReportTypeSelector component (value, label, description).
 */
test('builder page provides report types with value, label, and description for ReportTypeSelector', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get('/custom-reports');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('reportTypes', 4)
            ->where('reportTypes', function ($reportTypes) {
                // Each report type should have value, label, and description
                foreach ($reportTypes as $type) {
                    if (! isset($type['value'], $type['label'], $type['description'])) {
                        return false;
                    }
                }

                // Verify specific values exist
                $values = collect($reportTypes)->pluck('value')->toArray();
                expect($values)->toContain('capacity');
                expect($values)->toContain('assets');
                expect($values)->toContain('connections');
                expect($values)->toContain('audit_history');

                return true;
            })
        );
});

/**
 * Test 2: Configure endpoint returns fields grouped by category for ColumnSelector.
 *
 * This test verifies that the configure action returns fields with category information
 * that the ColumnSelector component can use to group checkboxes.
 */
test('configure endpoint returns fields with category grouping for ColumnSelector', function () {
    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    // Test for each report type
    foreach (ReportType::cases() as $reportType) {
        $response = $this->actingAs($admin)->get("/custom-reports/configure?report_type={$reportType->value}");

        $response->assertOk()
            ->assertJsonStructure([
                'fields' => [
                    '*' => [
                        'key',
                        'display_name',
                        'category',
                        'data_type',
                    ],
                ],
                'filters',
                'calculatedFields',
            ]);

        $data = $response->json();

        // Verify fields have categories
        $categories = collect($data['fields'])->pluck('category')->unique()->toArray();
        expect($categories)->not->toBeEmpty();

        // Verify each field has required properties for ColumnSelector
        foreach ($data['fields'] as $field) {
            expect($field)->toHaveKey('key');
            expect($field)->toHaveKey('display_name');
            expect($field)->toHaveKey('category');
        }

        // Verify calculated fields are marked appropriately
        $calculatedKeys = collect($data['calculatedFields'])->pluck('key')->toArray();
        foreach ($calculatedKeys as $calcKey) {
            $field = collect($data['fields'])->firstWhere('key', $calcKey);
            expect($field)->not->toBeNull();
            expect($field['is_calculated'] ?? false)->toBeTrue();
        }
    }
});

/**
 * Test 3: Preview requires at least one column to be selected.
 *
 * This test validates that the backend enforces the minimum column selection
 * requirement that corresponds to the ColumnSelector component validation.
 */
test('preview validation requires at least one column to be selected', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Test with no columns field at all
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'filters' => [],
        'sort' => [],
    ]);
    $response->assertSessionHasErrors('columns');

    // Test with empty columns array
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => [],
        'filters' => [],
        'sort' => [],
    ]);
    $response->assertSessionHasErrors('columns');

    // Test with at least one column - should succeed
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name'],
        'filters' => [],
        'sort' => [],
    ]);
    $response->assertSessionDoesntHaveErrors('columns');
    $response->assertOk();
});

/**
 * Test 4: Each report type provides distinct field configurations.
 *
 * This test ensures that selecting different report types returns different
 * field configurations for the ColumnSelector.
 */
test('each report type provides distinct field configurations', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $fieldsByType = [];

    foreach (ReportType::cases() as $reportType) {
        $response = $this->actingAs($admin)->get("/custom-reports/configure?report_type={$reportType->value}");
        $response->assertOk();

        $data = $response->json();
        $fieldsByType[$reportType->value] = collect($data['fields'])->pluck('key')->toArray();
    }

    // Verify each report type has some unique fields
    // Capacity should have rack-specific fields
    expect($fieldsByType['capacity'])->toContain('rack_name');
    expect($fieldsByType['capacity'])->toContain('u_height');
    expect($fieldsByType['capacity'])->toContain('utilization_percent');

    // Assets should have device-specific fields
    expect($fieldsByType['assets'])->toContain('asset_tag');
    expect($fieldsByType['assets'])->toContain('serial_number');
    expect($fieldsByType['assets'])->toContain('warranty_end_date');

    // Connections should have connection-specific fields
    expect($fieldsByType['connections'])->toContain('connection_id');
    expect($fieldsByType['connections'])->toContain('source_device');
    expect($fieldsByType['connections'])->toContain('cable_type');

    // Audit History should have audit-specific fields
    expect($fieldsByType['audit_history'])->toContain('audit_id');
    expect($fieldsByType['audit_history'])->toContain('audit_date');
    expect($fieldsByType['audit_history'])->toContain('finding_count');
});

/**
 * Test 5: Preview returns selected columns in the response.
 *
 * This test verifies that the preview action correctly returns column headers
 * based on user selection, which the Builder page will use.
 */
test('preview returns column headers based on selected columns', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $selectedColumns = ['rack_name', 'datacenter_name', 'utilization_percent'];

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => $selectedColumns,
        'filters' => [],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.columns', count($selectedColumns))
            ->where('previewData.columns', function ($columns) use ($selectedColumns) {
                $columnKeys = collect($columns)->pluck('key')->toArray();

                return $columnKeys === $selectedColumns;
            })
        );
});

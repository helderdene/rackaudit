<?php

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
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
 * Test 1: Builder page provides location filter options for mobile collapsible panel.
 *
 * This test verifies that the builder page returns datacenter options that can be used
 * in both mobile collapsible view and desktop inline view of CustomReportFilters.
 * The component renders as collapsible on mobile (lg:hidden) and inline on desktop (hidden lg:block).
 */
test('builder page provides location filter options for responsive filter panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenters for the filter options
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC Alpha']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC Beta']);

    // Create rooms to verify cascading data structure
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id, 'name' => 'Room A']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter1->id, 'name' => 'Room B']);

    $response = $this->actingAs($admin)->get('/custom-reports');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('datacenterOptions')
            ->where('datacenterOptions', function ($datacenters) {
                // Verify datacenter options have the structure needed for both mobile and desktop views
                foreach ($datacenters as $dc) {
                    if (! isset($dc['id'], $dc['name'])) {
                        return false;
                    }
                }

                // Should have at least the two datacenters we created
                expect(count($datacenters))->toBeGreaterThanOrEqual(2);

                return true;
            })
        );
});

/**
 * Test 2: Preview response includes pagination data for responsive pagination controls.
 *
 * This test ensures the preview response provides pagination data structure that
 * supports both compact mobile pagination and expanded desktop pagination views.
 * The PreviewTable component uses this for responsive pagination controls.
 */
test('preview provides pagination structure for responsive table controls', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create enough data to have multiple pages (25 per page default)
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Create 30 racks to ensure pagination is needed - insert directly to avoid factory unique issue
    for ($i = 0; $i < 30; $i++) {
        Rack::create([
            'name' => sprintf('TestRack%03d', $i),
            'row_id' => $row->id,
            'status' => RackStatus::Active,
            'u_height' => RackUHeight::U42,
            'position' => $i + 1,
        ]);
    }

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name', 'u_height'],
        'filters' => [],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.pagination')
            ->where('previewData.pagination', function ($pagination) {
                // Verify pagination has all required fields for responsive controls
                // Mobile view needs: current_page, last_page
                // Desktop view also uses: total, per_page
                expect($pagination)->toHaveKey('current_page');
                expect($pagination)->toHaveKey('last_page');
                expect($pagination)->toHaveKey('total');
                expect($pagination)->toHaveKey('per_page');

                // Verify pagination values are correct
                expect($pagination['current_page'])->toBe(1);
                expect($pagination['per_page'])->toBe(25);
                expect($pagination['total'])->toBeGreaterThanOrEqual(30);
                expect($pagination['last_page'])->toBeGreaterThan(1);

                return true;
            })
        );
});

/**
 * Test 3: Preview table data supports horizontal scrolling with proper column headers.
 *
 * This test verifies that the preview response includes column headers with proper
 * key and label pairs that the PreviewTable component uses for horizontal scrolling
 * table display on small screens. The first column can be sticky for better UX.
 */
test('preview includes column headers with keys for responsive table display', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
        'name' => 'Test-Rack-001',
    ]);

    // Request multiple columns to test table horizontal scroll behavior
    $manyColumns = [
        'rack_name',
        'datacenter_name',
        'room_name',
        'u_height',
        'used_u_space',
        'available_u_space',
        'utilization_percent',
    ];

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => $manyColumns,
        'filters' => [],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.columns', count($manyColumns))
            ->where('previewData.columns', function ($columns) {
                // Each column should have key and label for table header rendering
                foreach ($columns as $col) {
                    if (! isset($col['key'], $col['label'])) {
                        return false;
                    }

                    // Label should be user-friendly (not the raw key)
                    expect($col['label'])->not->toBe($col['key']);
                }

                // Verify first column is rack_name (will be sticky on mobile)
                expect($columns[0]['key'])->toBe('rack_name');

                return true;
            })
            ->has('previewData.data')
        );
});

/**
 * Test 4: Filter options structure supports type-specific responsive filter layouts.
 *
 * This test verifies that the configure endpoint returns filter options structured
 * appropriately for both mobile collapsible and desktop grid layouts.
 * TypeSpecificFilters component uses this for responsive filter rendering.
 */
test('configure returns filter options structured for responsive layouts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    // Test Assets report type which has multiple filter types
    $response = $this->actingAs($admin)->get('/custom-reports/configure?report_type=assets');

    $response->assertOk()
        ->assertJsonStructure([
            'fields',
            'filters',
            'calculatedFields',
        ]);

    $data = $response->json();

    // Verify filters have structure for responsive rendering
    if (! empty($data['filters'])) {
        foreach ($data['filters'] as $filterKey => $filterConfig) {
            // Each filter should have type and label for proper rendering
            expect($filterConfig)->toHaveKey('type');
            expect($filterConfig)->toHaveKey('label');

            // Dropdown filters should have options array
            if ($filterConfig['type'] === 'select' && isset($filterConfig['options'])) {
                foreach ($filterConfig['options'] as $option) {
                    // Options should have value and label for both mobile and desktop selects
                    expect($option)->toHaveKey('value');
                    expect($option)->toHaveKey('label');
                }
            }
        }
    }

    // Verify we have at least one filter configured for assets
    expect($data['filters'])->not->toBeEmpty();
});

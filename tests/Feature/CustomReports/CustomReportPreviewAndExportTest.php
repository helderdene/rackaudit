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
 * Test 1: PreviewTable receives dynamic columns based on user selection.
 *
 * This test verifies that the preview response includes column headers
 * that match the selected columns, enabling the PreviewTable component
 * to render dynamic headers.
 */
test('preview returns dynamic columns matching user selection for PreviewTable', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
        'name' => 'Rack-A1',
    ]);

    // Test with specific column selection
    $selectedColumns = ['rack_name', 'datacenter_name', 'u_height'];

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => $selectedColumns,
        'filters' => [],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.columns', 3)
            ->where('previewData.columns', function ($columns) use ($selectedColumns) {
                $columnKeys = collect($columns)->pluck('key')->toArray();

                // Verify columns match selection order
                return $columnKeys === $selectedColumns;
            })
            // Verify each column has a label for display
            ->where('previewData.columns', function ($columns) {
                foreach ($columns as $column) {
                    if (! isset($column['key'], $column['label'])) {
                        return false;
                    }
                }

                return true;
            })
        );
});

/**
 * Test 2: Preview pagination returns 25 rows per page.
 *
 * This test verifies that the preview endpoint correctly paginates data
 * at 25 rows per page, matching the PreviewTable component requirements.
 */
test('preview paginates data at 25 rows per page for PreviewTable', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data - more than 25 racks
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Create 30 racks using direct insertion to avoid factory unique constraints
    for ($i = 1; $i <= 30; $i++) {
        Rack::create([
            'row_id' => $row->id,
            'status' => RackStatus::Active,
            'u_height' => RackUHeight::U42,
            'name' => sprintf('Pagination-Rack-%03d', $i),
            'position' => $i,
        ]);
    }

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name'],
        'filters' => [],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 25)  // First page should have 25 records
            ->has('previewData.pagination')
            ->where('previewData.pagination.per_page', 25)
            ->where('previewData.pagination.total', 30)
            ->where('previewData.pagination.last_page', 2)
            ->where('previewData.pagination.current_page', 1)
        );

    // Test second page
    $response2 = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name'],
        'filters' => [],
        'sort' => [],
        'page' => 2,
    ]);

    $response2->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('previewData.data', 5)  // Second page should have remaining 5 records
            ->where('previewData.pagination.current_page', 2)
        );
});

/**
 * Test 3: Export PDF endpoint accepts POST request with configuration.
 *
 * This test verifies that the PDF export endpoint correctly accepts
 * a POST request with the report configuration payload.
 */
test('export PDF endpoint accepts POST request with report configuration', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $response = $this->actingAs($admin)->post('/custom-reports/export/pdf', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name'],
        'filters' => [],
        'sort' => [
            ['column' => 'rack_name', 'direction' => 'asc'],
        ],
    ]);

    // Should return a successful response (may be streamed or binary)
    $response->assertSuccessful();

    // Check content type is PDF
    $contentType = $response->headers->get('Content-Type');
    expect($contentType)->toContain('pdf');
});

/**
 * Test 4: Export CSV endpoint accepts POST request with configuration.
 *
 * This test verifies that the CSV export endpoint correctly accepts
 * a POST request and returns a CSV file.
 */
test('export CSV endpoint accepts POST request with report configuration', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $response = $this->actingAs($admin)->post('/custom-reports/export/csv', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name'],
        'filters' => [],
        'sort' => [],
    ]);

    // Should return a file download response
    $response->assertOk();

    // Check content disposition header for CSV download
    $contentDisposition = $response->headers->get('Content-Disposition');
    expect($contentDisposition)->toContain('.csv');
});

/**
 * Test 5: Export JSON endpoint returns structured JSON with metadata.
 *
 * This test verifies that the JSON export endpoint returns a properly
 * structured response with data and metadata.
 */
test('export JSON endpoint returns structured data with metadata', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'DC Alpha']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
        'name' => 'Rack-001',
    ]);

    $response = $this->actingAs($admin)->post('/custom-reports/export/json', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name'],
        'filters' => [],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'report_type',
            'report_label',
            'generated_at',
            'generated_by',
            'columns',
            'filters',
            'sort',
            'group_by',
            'data',
            'total',
        ]);

    $data = $response->json();

    // Verify metadata
    expect($data['report_type'])->toBe('capacity');
    expect($data['report_label'])->toBe('Capacity Report');
    expect($data['generated_by'])->toBe($admin->name);

    // Verify data structure
    expect($data['data'])->toBeArray();
    expect($data['total'])->toBeGreaterThanOrEqual(1);

    // Verify columns in data match selection
    if (count($data['data']) > 0) {
        $firstRow = $data['data'][0];
        expect($firstRow)->toHaveKey('rack_name');
        expect($firstRow)->toHaveKey('datacenter_name');
    }
});

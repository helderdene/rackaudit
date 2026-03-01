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
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check since Vue components are created in later task groups
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: Index page returns available report types and initial configuration.
 */
test('index page returns available report types and initial configuration', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a datacenter for the dropdown options
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);

    $response = $this->actingAs($admin)->get('/custom-reports');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('reportTypes')
            ->has('datacenterOptions')
            ->where('reportTypes', function ($reportTypes) {
                // Should have all 4 report types
                return count($reportTypes) === 4 &&
                    collect($reportTypes)->pluck('value')->contains('capacity') &&
                    collect($reportTypes)->pluck('value')->contains('assets') &&
                    collect($reportTypes)->pluck('value')->contains('connections') &&
                    collect($reportTypes)->pluck('value')->contains('audit_history');
            })
        );
});

/**
 * Test 2: Configure action returns fields/filters for selected report type.
 */
test('configure action returns fields and filters for selected report type', function () {
    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    $response = $this->actingAs($admin)->get('/custom-reports/configure?report_type=capacity');

    $response->assertOk()
        ->assertJsonStructure([
            'fields',
            'filters',
            'calculatedFields',
        ])
        ->assertJson(function ($json) {
            $json->has('fields')
                ->has('filters')
                ->has('calculatedFields');

            return true;
        });

    $data = $response->json();

    // Verify Capacity report has expected fields
    $fieldKeys = collect($data['fields'])->pluck('key')->toArray();
    expect($fieldKeys)->toContain('rack_name');
    expect($fieldKeys)->toContain('datacenter_name');
    expect($fieldKeys)->toContain('u_height');
    expect($fieldKeys)->toContain('utilization_percent');

    // Verify filters include datacenter_id
    expect($data['filters'])->toHaveKey('datacenter_id');
});

/**
 * Test 3: Preview action returns paginated data based on configuration.
 */
test('preview action returns paginated data based on configuration', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    Rack::factory()->count(3)->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name', 'u_height'],
        'filters' => [],
        'sort' => [['column' => 'rack_name', 'direction' => 'asc']],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData')
            ->has('previewData.data')
            ->has('previewData.pagination')
            ->where('previewData.pagination.per_page', 25)
        );
});

/**
 * Test 4: Export actions return correct response types.
 */
test('export actions return correct response types', function () {
    Storage::fake('local');

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
    ]);

    // Test CSV export
    $csvResponse = $this->actingAs($admin)->post('/custom-reports/export/csv', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name', 'u_height'],
        'filters' => [],
        'sort' => [],
    ]);

    $csvResponse->assertOk();
    $csvResponse->assertDownload();
    $contentDisposition = $csvResponse->headers->get('content-disposition');
    expect($contentDisposition)->toContain('.csv');

    // Test JSON export
    $jsonResponse = $this->actingAs($admin)->post('/custom-reports/export/json', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name', 'u_height'],
        'filters' => [],
        'sort' => [],
    ]);

    $jsonResponse->assertOk();
    $jsonResponse->assertJsonStructure([
        'report_type',
        'generated_at',
        'columns',
        'data',
        'total',
    ]);
});

/**
 * Test 5: Role-based access control restricts access appropriately.
 */
test('role-based access control restricts to IT Manager, Administrator, and Auditor only', function () {
    // Create users with different roles
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');

    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    // Administrator should have access
    $this->actingAs($admin)->get('/custom-reports')->assertOk();

    // IT Manager should have access
    $this->actingAs($itManager)->get('/custom-reports')->assertOk();

    // Auditor should have access
    $this->actingAs($auditor)->get('/custom-reports')->assertOk();

    // Operator should NOT have access
    $this->actingAs($operator)->get('/custom-reports')->assertForbidden();

    // Viewer should NOT have access
    $this->actingAs($viewer)->get('/custom-reports')->assertForbidden();

    // Unauthenticated users should be redirected (to login or otherwise handled)
    // The role middleware may return 403 or redirect - check that access is denied
    $response = $this->get('/custom-reports');
    // Unauthenticated should either redirect to login or return forbidden
    expect($response->status())->toBeIn([302, 403]);
});

/**
 * Test 6: Form validation enforces required fields and valid values.
 */
test('form validation enforces required fields and valid values', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Test missing report_type
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'columns' => ['rack_name'],
        'filters' => [],
        'sort' => [],
    ]);
    $response->assertSessionHasErrors('report_type');

    // Test missing columns
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'filters' => [],
        'sort' => [],
    ]);
    $response->assertSessionHasErrors('columns');

    // Test empty columns array
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => [],
        'filters' => [],
        'sort' => [],
    ]);
    $response->assertSessionHasErrors('columns');

    // Test invalid report_type
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'invalid_type',
        'columns' => ['rack_name'],
        'filters' => [],
        'sort' => [],
    ]);
    $response->assertSessionHasErrors('report_type');

    // Test sort limit (max 3 columns)
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name'],
        'filters' => [],
        'sort' => [
            ['column' => 'rack_name', 'direction' => 'asc'],
            ['column' => 'datacenter_name', 'direction' => 'desc'],
            ['column' => 'u_height', 'direction' => 'asc'],
            ['column' => 'utilization_percent', 'direction' => 'desc'], // 4th - should fail
        ],
    ]);
    $response->assertSessionHasErrors('sort');
});

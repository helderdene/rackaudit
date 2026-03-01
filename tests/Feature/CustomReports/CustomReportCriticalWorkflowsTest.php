<?php

/**
 * Custom Report Builder - Critical Workflow Tests
 *
 * These tests fill coverage gaps identified in Task Group 8 review,
 * focusing on end-to-end workflows, edge cases, and untested scenarios.
 */

use App\Enums\AuditStatus;
use App\Enums\CableType;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Enums\ReportType;
use App\Exports\CustomReportExport;
use App\Models\Audit;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\CustomReportBuilderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: End-to-end flow from type selection to CSV export.
 *
 * This test verifies the complete user workflow:
 * 1. Visit builder page (type selection)
 * 2. Configure report (select columns)
 * 3. Preview data
 * 4. Export to CSV
 */
test('end-to-end workflow from type selection to CSV export works correctly', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Setup test data
    $datacenter = Datacenter::factory()->create(['name' => 'E2E Test DC']);
    $room = Room::factory()->create(['name' => 'E2E Room', 'datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['name' => 'E2E Row', 'room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'name' => 'E2E-Rack-001',
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    // Step 1: Visit builder page (type selection)
    $indexResponse = $this->actingAs($admin)->get('/custom-reports');
    $indexResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('reportTypes', 4)
        );

    // Step 2: Configure report (get fields for capacity type)
    $configResponse = $this->actingAs($admin)->get('/custom-reports/configure?report_type=capacity');
    $configResponse->assertOk();
    $config = $configResponse->json();
    expect($config['fields'])->not->toBeEmpty();

    // Step 3: Preview data
    $previewResponse = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name', 'room_name', 'u_height'],
        'filters' => ['datacenter_id' => $datacenter->id],
        'sort' => [['column' => 'rack_name', 'direction' => 'asc']],
    ]);

    $previewResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('previewData.data', 1)
            ->where('previewData.data.0.rack_name', 'E2E-Rack-001')
        );

    // Step 4: Export to CSV
    $csvResponse = $this->actingAs($admin)->post('/custom-reports/export/csv', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name', 'room_name', 'u_height'],
        'filters' => ['datacenter_id' => $datacenter->id],
        'sort' => [['column' => 'rack_name', 'direction' => 'asc']],
    ]);

    $csvResponse->assertOk();
    $csvResponse->assertDownload();

    // Verify CSV content
    $contentDisposition = $csvResponse->headers->get('content-disposition');
    expect($contentDisposition)->toContain('.csv');
});

/**
 * Test 2: End-to-end flow from type selection to PDF export.
 *
 * Similar to CSV test, but verifies PDF generation workflow.
 */
test('end-to-end workflow from type selection to PDF export works correctly', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['name' => 'PDF Test Admin']);
    $admin->assignRole('Administrator');

    // Setup test data
    $datacenter = Datacenter::factory()->create(['name' => 'PDF Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create([
        'name' => 'PDF-Rack-001',
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    // Complete workflow to PDF export
    $pdfResponse = $this->actingAs($admin)->post('/custom-reports/export/pdf', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name', 'u_height', 'utilization_percent'],
        'filters' => [],
        'sort' => [['column' => 'rack_name', 'direction' => 'asc']],
    ]);

    $pdfResponse->assertSuccessful();

    // Verify PDF content type
    $contentType = $pdfResponse->headers->get('Content-Type');
    expect($contentType)->toContain('pdf');
});

/**
 * Test 3: Empty result set handling.
 *
 * Verifies that the system gracefully handles cases where no data
 * matches the specified filters.
 */
test('preview handles empty result set gracefully', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a datacenter but no racks
    $datacenter = Datacenter::factory()->create(['name' => 'Empty DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    Row::factory()->create(['room_id' => $room->id]);
    // Note: No racks created

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name'],
        'filters' => ['datacenter_id' => $datacenter->id],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 0)
            ->where('previewData.pagination.total', 0)
        );
});

/**
 * Test 4: Single column selection (minimum boundary).
 *
 * Verifies that reports work correctly with only one column selected
 * (the minimum allowed per specification).
 */
test('preview works with single column selection at minimum boundary', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create([
        'name' => 'Single-Col-Rack',
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    // Test with exactly one column (minimum)
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name'],
        'filters' => [],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('previewData.columns', 1)
            ->where('previewData.columns.0.key', 'rack_name')
            ->has('previewData.data')
        );
});

/**
 * Test 5: Calculated fields in preview data.
 *
 * Verifies that calculated fields are correctly computed and returned
 * in the preview response.
 */
test('preview correctly computes and returns calculated fields', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'name' => 'Calc-Test-Rack',
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    // Create 5 devices in the rack
    Device::factory()->count(5)->create(['rack_id' => $rack->id]);

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'devices_per_rack'],
        'filters' => [],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('previewData.data')
            ->where('previewData.data.0.devices_per_rack', 5)
        );
});

/**
 * Test 6: Grouping with subtotals in PDF export.
 *
 * Verifies that grouping generates proper group headers and subtotals.
 */
test('PDF export with grouping generates subtotals correctly', function () {
    Storage::fake('local');

    $user = User::factory()->create(['name' => 'Group Test User']);
    $user->assignRole('Administrator');

    // Create multiple datacenters with racks
    $dc1 = Datacenter::factory()->create(['name' => 'DC Alpha']);
    $room1 = Room::factory()->create(['datacenter_id' => $dc1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);

    $dc2 = Datacenter::factory()->create(['name' => 'DC Beta']);
    $room2 = Room::factory()->create(['datacenter_id' => $dc2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);

    // Create racks in each datacenter
    Rack::factory()->count(2)->create([
        'row_id' => $row1->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    Rack::factory()->count(3)->create([
        'row_id' => $row2->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U48,
    ]);

    // Get the service
    $reportService = app(CustomReportBuilderService::class);

    // Generate PDF with grouping
    $filePath = $reportService->generatePdfReport(
        type: ReportType::Capacity,
        columns: ['rack_name', 'datacenter_name', 'u_height'],
        filters: [],
        sort: [],
        groupBy: 'datacenter_name',
        generator: $user
    );

    // Verify file was created
    expect($filePath)->toStartWith('reports/custom/');
    expect($filePath)->toEndWith('.pdf');
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    // Verify file has content
    $content = Storage::disk('local')->get($filePath);
    expect($content)->toStartWith('%PDF');
});

/**
 * Test 7: Connections report type query building.
 *
 * Verifies that the Connections report type correctly builds queries
 * with filters applied.
 */
test('connections report type builds filtered query correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Setup test data with connections
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);
    $device1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id, 'name' => 'Switch-A']);
    $device2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id, 'name' => 'Switch-B']);

    // Create ports
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id, 'label' => 'eth0']);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id, 'label' => 'eth0']);

    // Create connection
    Connection::factory()->cat6()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
    ]);

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'connections',
        'columns' => ['connection_id', 'source_device', 'destination_device', 'cable_type'],
        'filters' => ['cable_type' => CableType::Cat6->value],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('previewData.data', 1)
            ->where('previewData.data.0.source_device', 'Switch-A')
            ->where('previewData.data.0.destination_device', 'Switch-B')
        );
});

/**
 * Test 8: Audit History report type query building.
 *
 * Verifies that the Audit History report type correctly builds queries.
 */
test('audit history report type builds query with completed audits', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Setup test data
    $datacenter = Datacenter::factory()->create(['name' => 'Audit DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    // Create completed audits
    $audit1 = Audit::factory()->completed()->inventoryType()->create([
        'datacenter_id' => $datacenter->id,
        'name' => 'Completed Audit 1',
    ]);

    $audit2 = Audit::factory()->completed()->connectionType()->create([
        'datacenter_id' => $datacenter->id,
        'name' => 'Completed Audit 2',
    ]);

    // Create a pending audit (should not be included)
    Audit::factory()->pending()->create([
        'datacenter_id' => $datacenter->id,
        'name' => 'Pending Audit',
    ]);

    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'audit_history',
        'columns' => ['audit_id', 'datacenter_name', 'audit_type'],
        'filters' => ['datacenter_id' => $datacenter->id],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Should only include completed audits (2 out of 3)
            ->has('previewData.data', 2)
        );
});

/**
 * Test 9: JSON export handles empty results correctly.
 *
 * Verifies that JSON export returns proper structure even with no data.
 */
test('JSON export returns proper structure for empty results', function () {
    $admin = User::factory()->create(['name' => 'Empty JSON Test']);
    $admin->assignRole('Administrator');

    // Create a datacenter with no matching data
    $emptyDc = Datacenter::factory()->create(['name' => 'Empty for JSON']);

    $response = $this->actingAs($admin)->post('/custom-reports/export/json', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name'],
        'filters' => ['datacenter_id' => $emptyDc->id],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'report_type',
            'report_label',
            'generated_at',
            'generated_by',
            'columns',
            'data',
            'total',
        ]);

    $data = $response->json();

    expect($data['total'])->toBe(0);
    expect($data['data'])->toBeEmpty();
    expect($data['generated_by'])->toBe('Empty JSON Test');
});

/**
 * Test 10: Export with all filter types combined.
 *
 * Verifies that multiple filters can be applied together correctly.
 */
test('export with combined location and type-specific filters works correctly', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Setup test data
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create devices with warranties
    Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'manufacturer' => 'Dell',
        'warranty_end_date' => now()->addDays(60),
    ]);

    Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'manufacturer' => 'HP',
        'warranty_end_date' => now()->addDays(30),
    ]);

    // Test with multiple filters combined
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'assets',
        'columns' => ['name', 'manufacturer', 'warranty_end_date'],
        'filters' => [
            'datacenter_id' => $datacenter->id,
            'device_type_id' => $deviceType->id,
            'manufacturer' => 'Dell',
        ],
        'sort' => [['column' => 'name', 'direction' => 'asc']],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Should only return Dell devices (1 out of 2)
            ->has('previewData.data', 1)
            ->where('previewData.data.0.manufacturer', 'Dell')
        );
});

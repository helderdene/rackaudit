<?php

use App\Enums\DeviceLifecycleStatus;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Enums\ReportType;
use App\Exports\CustomReportExport;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\CustomReportBuilderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

/**
 * Test 1: CustomReportExport generates CSV with dynamic columns.
 */
test('custom report export generates CSV with dynamic columns', function () {
    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'Test Datacenter']);
    $room = Room::factory()->create(['name' => 'Room A', 'datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['name' => 'Row 1', 'room_id' => $room->id]);

    $rack1 = Rack::factory()->create([
        'name' => 'Rack-001',
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $rack2 = Rack::factory()->create([
        'name' => 'Rack-002',
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U48,
    ]);

    // Get the service
    $reportService = app(CustomReportBuilderService::class);

    // Create export with specific columns only
    $export = new CustomReportExport(
        reportType: ReportType::Capacity,
        columns: ['rack_name', 'datacenter_name', 'u_height'],
        filters: [],
        sort: [['column' => 'rack_name', 'direction' => 'asc']],
        groupBy: null,
        reportService: $reportService
    );

    // Store the export
    Excel::store($export, 'custom_report.csv', 'local');

    // Load and verify the spreadsheet
    $filePath = Storage::disk('local')->path('custom_report.csv');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Verify headings - should only have the 3 selected columns
    $headings = [
        $sheet->getCellByColumnAndRow(1, 1)->getValue(),
        $sheet->getCellByColumnAndRow(2, 1)->getValue(),
        $sheet->getCellByColumnAndRow(3, 1)->getValue(),
    ];

    expect($headings)->toContain('Rack Name');
    expect($headings)->toContain('Datacenter');
    expect($headings)->toContain('Total U Height');

    // Verify data rows
    $rowData1 = $sheet->getCellByColumnAndRow(1, 2)->getValue();
    expect($rowData1)->toBeIn(['Rack-001', 'Rack-002']);

    // Count data rows (excluding header)
    $rowCount = 0;
    $row = 2;
    while ($sheet->getCellByColumnAndRow(1, $row)->getValue() !== null) {
        $rowCount++;
        $row++;
    }

    expect($rowCount)->toBe(2);
});

/**
 * Test 2: CustomReportExport handles calculated fields in transformRow.
 */
test('custom report export includes calculated fields correctly', function () {
    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    $rack = Rack::factory()->create([
        'name' => 'Test Rack',
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    // Create 3 devices in the rack
    Device::factory()->count(3)->create(['rack_id' => $rack->id]);

    // Get the service
    $reportService = app(CustomReportBuilderService::class);

    // Create export with calculated field
    $export = new CustomReportExport(
        reportType: ReportType::Capacity,
        columns: ['rack_name', 'devices_per_rack'],
        filters: [],
        sort: [],
        groupBy: null,
        reportService: $reportService
    );

    // Store the export
    Excel::store($export, 'custom_report_calculated.xlsx', 'local');

    // Load and verify
    $filePath = Storage::disk('local')->path('custom_report_calculated.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Verify headings include the calculated field
    $heading1 = $sheet->getCellByColumnAndRow(1, 1)->getValue();
    $heading2 = $sheet->getCellByColumnAndRow(2, 1)->getValue();

    expect($heading1)->toBe('Rack Name');
    expect($heading2)->toBe('Devices per Rack');

    // Verify the calculated value
    $deviceCount = $sheet->getCellByColumnAndRow(2, 2)->getValue();
    expect((int) $deviceCount)->toBe(3);
});

/**
 * Test 3: PDF template renders with dynamic data.
 */
test('PDF export generates file with dynamic data', function () {
    // Create test user
    $user = User::factory()->create(['name' => 'Test Admin']);
    $user->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'PDF Test DC']);
    $room = Room::factory()->create(['name' => 'PDF Room', 'datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['name' => 'PDF Row', 'room_id' => $room->id]);

    Rack::factory()->create([
        'name' => 'PDF-Rack-001',
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    // Get the service
    $reportService = app(CustomReportBuilderService::class);

    // Generate PDF
    $filePath = $reportService->generatePdfReport(
        type: ReportType::Capacity,
        columns: ['rack_name', 'datacenter_name', 'u_height'],
        filters: [],
        sort: [],
        groupBy: null,
        generator: $user
    );

    // Verify the file was created
    expect($filePath)->toStartWith('reports/custom/');
    expect($filePath)->toEndWith('.pdf');
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    // Verify file has content (PDF header starts with %PDF)
    $content = Storage::disk('local')->get($filePath);
    expect($content)->toStartWith('%PDF');
});

/**
 * Test 4: JSON export returns correct structure with metadata.
 */
test('JSON export returns correct structure with metadata', function () {
    // Create test user
    $user = User::factory()->create(['name' => 'JSON Test User']);
    $user->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'JSON Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    Rack::factory()->create([
        'name' => 'JSON-Rack',
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Test the JSON export endpoint
    $response = $this->actingAs($user)->post('/custom-reports/export/json', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name', 'u_height'],
        'filters' => [],
        'sort' => [],
    ]);

    $response->assertOk();

    $data = $response->json();

    // Verify structure
    expect($data)->toHaveKey('report_type');
    expect($data)->toHaveKey('report_label');
    expect($data)->toHaveKey('generated_at');
    expect($data)->toHaveKey('generated_by');
    expect($data)->toHaveKey('columns');
    expect($data)->toHaveKey('data');
    expect($data)->toHaveKey('total');

    // Verify content
    expect($data['report_type'])->toBe('capacity');
    expect($data['report_label'])->toBe('Capacity Report');
    expect($data['generated_by'])->toBe('JSON Test User');
    expect($data['total'])->toBe(1);

    // Verify columns structure
    expect($data['columns'])->toBeArray();
    expect($data['columns'][0])->toHaveKey('key');
    expect($data['columns'][0])->toHaveKey('label');

    // Verify data contains expected fields
    expect($data['data'])->toHaveCount(1);
    expect($data['data'][0])->toHaveKey('rack_name');
    expect($data['data'][0]['rack_name'])->toBe('JSON-Rack');
});

/**
 * Test 5: Export for Assets report type with warranty calculated field.
 */
test('assets report export includes warranty calculated field correctly', function () {
    // Create test data
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    $device = Device::factory()->create([
        'name' => 'Test Server',
        'asset_tag' => 'SRV-001',
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'warranty_end_date' => now()->addDays(30),
        'purchase_date' => now()->subYears(2),
    ]);

    // Get the service
    $reportService = app(CustomReportBuilderService::class);

    // Create export with warranty calculated field
    $export = new CustomReportExport(
        reportType: ReportType::Assets,
        columns: ['asset_tag', 'name', 'days_until_warranty_expiration', 'age_in_years'],
        filters: [],
        sort: [],
        groupBy: null,
        reportService: $reportService
    );

    // Store the export
    Excel::store($export, 'assets_report.xlsx', 'local');

    // Load and verify
    $filePath = Storage::disk('local')->path('assets_report.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Verify headings
    $heading1 = $sheet->getCellByColumnAndRow(1, 1)->getValue();
    $heading2 = $sheet->getCellByColumnAndRow(2, 1)->getValue();
    $heading3 = $sheet->getCellByColumnAndRow(3, 1)->getValue();
    $heading4 = $sheet->getCellByColumnAndRow(4, 1)->getValue();

    expect($heading1)->toBe('Asset Tag');
    expect($heading2)->toBe('Device Name');
    expect($heading3)->toBe('Days Until Warranty Expiration');
    expect($heading4)->toBe('Age (Years)');

    // Verify data values
    $assetTag = $sheet->getCellByColumnAndRow(1, 2)->getValue();
    $daysWarranty = $sheet->getCellByColumnAndRow(3, 2)->getValue();
    $ageYears = $sheet->getCellByColumnAndRow(4, 2)->getValue();

    expect($assetTag)->toBe('SRV-001');
    // Days until warranty should be around 30 (allow for some variance)
    expect((int) $daysWarranty)->toBeGreaterThanOrEqual(29);
    expect((int) $daysWarranty)->toBeLessThanOrEqual(31);
    // Age should be around 2 years
    expect((float) $ageYears)->toBeGreaterThanOrEqual(1.9);
    expect((float) $ageYears)->toBeLessThanOrEqual(2.1);
});

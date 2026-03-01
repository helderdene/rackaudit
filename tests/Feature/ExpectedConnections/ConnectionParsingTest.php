<?php

use App\Actions\ExpectedConnections\ParseConnectionsAction;
use App\Exports\Templates\ConnectionDataSheet;
use App\Exports\Templates\ConnectionInstructionsSheet;
use App\Exports\Templates\ConnectionTemplateExport;
use App\Imports\ConnectionFileImport;
use App\Models\Device;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\User;
use App\Services\FuzzyMatchingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Task 2.1: Tests for parsing functionality
 */

test('Excel template generation creates correct columns and instructions sheet', function () {
    $export = new ConnectionTemplateExport;

    // Verify main sheet headings
    $headings = $export->headings();
    expect($headings)->toBe([
        'Source Device',
        'Source Port',
        'Dest Device',
        'Dest Port',
        'Cable Type',
        'Cable Length',
    ]);

    // Verify the export has multiple sheets (main + instructions)
    $sheets = $export->sheets();
    expect($sheets)->toHaveCount(2);

    // First sheet should be the data sheet
    expect($sheets[0])->toBeInstanceOf(ConnectionDataSheet::class);

    // Second sheet should be the instructions sheet
    expect($sheets[1])->toBeInstanceOf(ConnectionInstructionsSheet::class);
});

test('CSV template generation creates correct header row', function () {
    $user = User::factory()->create()->assignRole('Administrator');

    $response = $this->actingAs($user)->get('/templates/connections/csv');

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    // For streamed responses, capture the content using output buffering
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    $lines = explode("\n", $content);
    $headers = str_getcsv($lines[0]);

    expect($headers)->toContain('Source Device');
    expect($headers)->toContain('Source Port');
    expect($headers)->toContain('Dest Device');
    expect($headers)->toContain('Dest Port');
    expect($headers)->toContain('Cable Type');
    expect($headers)->toContain('Cable Length');
});

test('parsing valid Excel file extracts all columns correctly', function () {
    // Create test devices and ports
    $sourceDevice = Device::factory()->create(['name' => 'Server-001']);
    $destDevice = Device::factory()->create(['name' => 'Switch-001']);
    Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => 'eth0']);
    Port::factory()->ethernet()->create(['device_id' => $destDevice->id, 'label' => 'port-1']);

    // Create a test Excel file with valid data
    $tempPath = createTestExcelFile([
        ['Source Device', 'Source Port', 'Dest Device', 'Dest Port', 'Cable Type', 'Cable Length'],
        ['Server-001', 'eth0', 'Switch-001', 'port-1', 'Cat6', '3.5'],
    ]);

    $import = new ConnectionFileImport;
    $import->loadFile($tempPath);
    $result = $import->parse();

    expect($result['success'])->toBeTrue();
    expect($result['rows'])->toHaveCount(1);
    expect($result['rows'][0]['source_device'])->toBe('Server-001');
    expect($result['rows'][0]['source_port'])->toBe('eth0');
    expect($result['rows'][0]['dest_device'])->toBe('Switch-001');
    expect($result['rows'][0]['dest_port'])->toBe('port-1');
    expect($result['rows'][0]['cable_type'])->toBe('Cat6');
    expect($result['rows'][0]['cable_length'])->toBe('3.5');
    expect($result['rows'][0]['row_number'])->toBe(2);

    @unlink($tempPath);
});

test('parsing valid CSV file extracts all columns correctly', function () {
    // Create test devices and ports
    $sourceDevice = Device::factory()->create(['name' => 'Server-002']);
    $destDevice = Device::factory()->create(['name' => 'Switch-002']);
    Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => 'eth1']);
    Port::factory()->ethernet()->create(['device_id' => $destDevice->id, 'label' => 'port-2']);

    // Create a test CSV file with valid data
    $csvContent = "Source Device,Source Port,Dest Device,Dest Port,Cable Type,Cable Length\n";
    $csvContent .= "Server-002,eth1,Switch-002,port-2,Cat6a,5.0\n";

    $tempPath = sys_get_temp_dir().'/test_connections_'.uniqid().'.csv';
    file_put_contents($tempPath, $csvContent);

    $import = new ConnectionFileImport;
    $import->loadFile($tempPath, 'csv');
    $result = $import->parse();

    expect($result['success'])->toBeTrue();
    expect($result['rows'])->toHaveCount(1);
    expect($result['rows'][0]['source_device'])->toBe('Server-002');
    expect($result['rows'][0]['source_port'])->toBe('eth1');
    expect($result['rows'][0]['dest_device'])->toBe('Switch-002');
    expect($result['rows'][0]['dest_port'])->toBe('port-2');
    expect($result['rows'][0]['cable_type'])->toBe('Cat6a');
    expect($result['rows'][0]['cable_length'])->toBe('5.0');
    expect($result['rows'][0]['row_number'])->toBe(2);

    @unlink($tempPath);
});

test('parsing rejects files missing required columns', function () {
    // Create a CSV file missing the required Dest Device column
    $csvContent = "Source Device,Source Port,Dest Port,Cable Type\n";
    $csvContent .= "Server-001,eth0,port-1,Cat6\n";

    $tempPath = sys_get_temp_dir().'/test_missing_columns_'.uniqid().'.csv';
    file_put_contents($tempPath, $csvContent);

    $import = new ConnectionFileImport;
    $import->loadFile($tempPath, 'csv');
    $result = $import->parse();

    expect($result['success'])->toBeFalse();
    expect($result['errors'])->toContain('Missing required column: Dest Device');

    @unlink($tempPath);
});

test('parsing captures row-level errors for invalid data', function () {
    // Create a CSV file with some invalid rows
    $csvContent = "Source Device,Source Port,Dest Device,Dest Port,Cable Type,Cable Length\n";
    $csvContent .= "Server-001,eth0,Switch-001,port-1,Cat6,3.5\n"; // Valid
    $csvContent .= ",eth1,Switch-001,port-2,Cat6,2.0\n"; // Missing source device
    $csvContent .= "Server-002,eth2,,port-3,Cat6,1.5\n"; // Missing dest device

    $tempPath = sys_get_temp_dir().'/test_invalid_rows_'.uniqid().'.csv';
    file_put_contents($tempPath, $csvContent);

    $import = new ConnectionFileImport;
    $import->loadFile($tempPath, 'csv');
    $result = $import->parse();

    expect($result['success'])->toBeTrue();
    expect($result['rows'])->toHaveCount(3);
    expect($result['row_errors'])->toHaveCount(2);
    expect($result['row_errors'][0]['row_number'])->toBe(3);
    expect($result['row_errors'][0]['field'])->toBe('source_device');
    expect($result['row_errors'][1]['row_number'])->toBe(4);
    expect($result['row_errors'][1]['field'])->toBe('dest_device');

    @unlink($tempPath);
});

test('fuzzy matching calculates confidence scores correctly', function () {
    // Create test devices
    $device1 = Device::factory()->create(['name' => 'Server-001']);
    Device::factory()->create(['name' => 'Server-002']);
    Device::factory()->create(['name' => 'Switch-001']);

    $fuzzyService = new FuzzyMatchingService;

    // Exact match should return 100
    $result = $fuzzyService->matchDevice('Server-001');
    expect($result['confidence'])->toBe(100);
    expect($result['device_id'])->toBe($device1->id);
    expect($result['match_type'])->toBe('exact');

    // Similar match should return suggested (above threshold)
    $result = $fuzzyService->matchDevice('Server-01');
    expect($result['confidence'])->toBeGreaterThanOrEqual(FuzzyMatchingService::MATCH_THRESHOLD);
    expect($result['match_type'])->toBe('suggested');

    // Very different should be unrecognized (below threshold)
    $result = $fuzzyService->matchDevice('CompletelyDifferentName123');
    expect($result['confidence'])->toBeLessThan(FuzzyMatchingService::MATCH_THRESHOLD);
    expect($result['match_type'])->toBe('unrecognized');
});

test('file size limit enforcement blocks oversized files', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    // Update the implementation file to simulate an oversized file
    $implementationFile->update(['file_size' => 6 * 1024 * 1024]); // 6 MB

    $action = new ParseConnectionsAction;
    $result = $action->execute($implementationFile);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('exceeds the maximum');
});

/**
 * Helper function to create test Excel file.
 *
 * @param  array<int, array<string>>  $content
 */
function createTestExcelFile(array $content): string
{
    $tempPath = sys_get_temp_dir().'/test_connections_'.uniqid().'.xlsx';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($content as $rowIndex => $row) {
        foreach ($row as $colIndex => $value) {
            $sheet->setCellValue(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1).($rowIndex + 1),
                $value
            );
        }
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($tempPath);

    return $tempPath;
}

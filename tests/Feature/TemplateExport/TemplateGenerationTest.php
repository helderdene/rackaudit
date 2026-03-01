<?php

use App\Enums\DeviceLifecycleStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\RoomType;
use App\Exports\Templates\CombinedTemplateExport;
use App\Exports\Templates\DatacenterTemplateExport;
use App\Exports\Templates\DeviceTemplateExport;
use App\Exports\Templates\PortTemplateExport;
use App\Exports\Templates\RoomTemplateExport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('generates datacenter template with correct column headers', function () {
    Storage::fake('local');

    $export = new DatacenterTemplateExport;
    Excel::store($export, 'datacenter_template.xlsx', 'local');

    Storage::disk('local')->assertExists('datacenter_template.xlsx');

    // Read the file and verify headers
    $filePath = Storage::disk('local')->path('datacenter_template.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    $expectedHeaders = [
        'name',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country',
        'company_name',
        'primary_contact_name',
        'primary_contact_email',
        'primary_contact_phone',
        'secondary_contact_name',
        'secondary_contact_email',
        'secondary_contact_phone',
    ];

    foreach ($expectedHeaders as $index => $header) {
        $cellValue = $sheet->getCellByColumnAndRow($index + 1, 1)->getValue();
        expect($cellValue)->toBe($header);
    }

    // Verify required columns are highlighted with yellow background (FFE699)
    $requiredHeaders = ['name', 'address_line_1', 'city', 'state_province', 'postal_code', 'country', 'primary_contact_name', 'primary_contact_email', 'primary_contact_phone'];
    foreach ($requiredHeaders as $requiredHeader) {
        $index = array_search($requiredHeader, $expectedHeaders);
        $cell = $sheet->getCellByColumnAndRow($index + 1, 1);
        $bgColor = $cell->getStyle()->getFill()->getStartColor()->getRGB();
        expect($bgColor)->toBe('FFE699', "Expected '{$requiredHeader}' header to have yellow background for required indication");
    }
});

it('generates room template with type dropdown containing RoomType enum values', function () {
    Storage::fake('local');

    $export = new RoomTemplateExport;
    Excel::store($export, 'room_template.xlsx', 'local');

    Storage::disk('local')->assertExists('room_template.xlsx');

    // Read the file and verify headers
    $filePath = Storage::disk('local')->path('room_template.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Verify 'type' column header exists at column C (index 3)
    $typeHeader = $sheet->getCellByColumnAndRow(3, 1)->getValue();
    expect($typeHeader)->toBe('type');

    // Verify data validation dropdown is set for type column
    $dataValidation = $sheet->getDataValidation('C2');
    expect($dataValidation)->not->toBeNull();
    expect($dataValidation->getType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);

    // Verify dropdown contains RoomType enum labels
    $dropdownFormula = $dataValidation->getFormula1();
    foreach (RoomType::cases() as $case) {
        expect($dropdownFormula)->toContain($case->label());
    }
});

it('generates device template with all required enum dropdowns', function () {
    Storage::fake('local');

    $export = new DeviceTemplateExport;
    Excel::store($export, 'device_template.xlsx', 'local');

    Storage::disk('local')->assertExists('device_template.xlsx');

    $filePath = Storage::disk('local')->path('device_template.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Get column positions from headers
    $headers = [];
    $col = 1;
    while ($value = $sheet->getCellByColumnAndRow($col, 1)->getValue()) {
        $headers[$value] = $col;
        $col++;
    }

    // Verify lifecycle_status dropdown
    $lifecycleCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headers['lifecycle_status']);
    $dataValidation = $sheet->getDataValidation($lifecycleCol.'2');
    expect($dataValidation->getType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
    $dropdownFormula = $dataValidation->getFormula1();
    foreach (DeviceLifecycleStatus::cases() as $case) {
        expect($dropdownFormula)->toContain($case->label());
    }

    // Verify depth dropdown
    $depthCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headers['depth']);
    $dataValidation = $sheet->getDataValidation($depthCol.'2');
    expect($dataValidation->getType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);

    // Verify width_type dropdown
    $widthCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headers['width_type']);
    $dataValidation = $sheet->getDataValidation($widthCol.'2');
    expect($dataValidation->getType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);

    // Verify rack_face dropdown
    $faceCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headers['rack_face']);
    $dataValidation = $sheet->getDataValidation($faceCol.'2');
    expect($dataValidation->getType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
});

it('generates templates with example data row', function () {
    Storage::fake('local');

    $export = new DatacenterTemplateExport;
    Excel::store($export, 'datacenter_template.xlsx', 'local');

    $filePath = Storage::disk('local')->path('datacenter_template.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Row 2 should contain example data
    $exampleName = $sheet->getCellByColumnAndRow(1, 2)->getValue();
    expect($exampleName)->toBe('Example Datacenter');

    $exampleCity = $sheet->getCellByColumnAndRow(4, 2)->getValue();
    expect($exampleCity)->toBe('New York');
});

it('generates combined template with all entity type columns', function () {
    Storage::fake('local');

    $export = new CombinedTemplateExport;
    Excel::store($export, 'combined_template.xlsx', 'local');

    Storage::disk('local')->assertExists('combined_template.xlsx');

    $filePath = Storage::disk('local')->path('combined_template.xlsx');
    $spreadsheet = IOFactory::load($filePath);

    // Verify multiple sheets exist for different entity types
    $sheetNames = $spreadsheet->getSheetNames();
    expect($sheetNames)->toContain('Datacenters');
    expect($sheetNames)->toContain('Rooms');
    expect($sheetNames)->toContain('Rows');
    expect($sheetNames)->toContain('Racks');
    expect($sheetNames)->toContain('Devices');
    expect($sheetNames)->toContain('Ports');
});

it('generates port template with type-specific subtypes and directions', function () {
    Storage::fake('local');

    $export = new PortTemplateExport;
    Excel::store($export, 'port_template.xlsx', 'local');

    Storage::disk('local')->assertExists('port_template.xlsx');

    $filePath = Storage::disk('local')->path('port_template.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Get column positions from headers
    $headers = [];
    $col = 1;
    while ($value = $sheet->getCellByColumnAndRow($col, 1)->getValue()) {
        $headers[$value] = $col;
        $col++;
    }

    // Verify type dropdown contains all PortType values
    $typeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headers['type']);
    $dataValidation = $sheet->getDataValidation($typeCol.'2');
    expect($dataValidation->getType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
    $typeFormula = $dataValidation->getFormula1();
    foreach (PortType::cases() as $case) {
        expect($typeFormula)->toContain($case->label());
    }

    // Verify subtype dropdown contains all PortSubtype values (note about type compatibility)
    $subtypeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headers['subtype']);
    $subtypeValidation = $sheet->getDataValidation($subtypeCol.'2');
    expect($subtypeValidation->getType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);

    // Verify status dropdown
    $statusCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headers['status']);
    $statusValidation = $sheet->getDataValidation($statusCol.'2');
    expect($statusValidation->getType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
});

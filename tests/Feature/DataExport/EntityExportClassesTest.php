<?php

use App\Exports\DatacenterExport;
use App\Exports\DeviceExport;
use App\Exports\PortExport;
use App\Exports\RackExport;
use App\Exports\RoomExport;
use App\Exports\RowExport;
use App\Exports\Templates\DatacenterTemplateExport;
use App\Exports\Templates\DeviceTemplateExport;
use App\Exports\Templates\PortTemplateExport;
use App\Exports\Templates\RackTemplateExport;
use App\Exports\Templates\RoomTemplateExport;
use App\Exports\Templates\RowTemplateExport;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('datacenter export generates correct columns matching template headings', function () {
    $datacenter = Datacenter::factory()->create([
        'name' => 'Test Datacenter',
        'city' => 'Los Angeles',
        'country' => 'USA',
    ]);

    $export = new DatacenterExport();
    Excel::store($export, 'datacenters_export.xlsx', 'local');

    Storage::disk('local')->assertExists('datacenters_export.xlsx');

    // Verify headings match template
    $templateExport = new DatacenterTemplateExport();
    expect($export->headings())->toBe($templateExport->headings());

    // Verify data is exported
    $filePath = Storage::disk('local')->path('datacenters_export.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Row 2 should contain the datacenter data
    $exportedName = $sheet->getCellByColumnAndRow(1, 2)->getValue();
    expect($exportedName)->toBe('Test Datacenter');
});

test('device export applies datacenter filter correctly', function () {
    // Create two datacenters with devices
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC One']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC Two']);

    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);

    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);

    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);

    $deviceType = DeviceType::factory()->create();

    // Create devices in both datacenters
    Device::factory()->count(3)->create([
        'rack_id' => $rack1->id,
        'device_type_id' => $deviceType->id,
    ]);
    Device::factory()->count(5)->create([
        'rack_id' => $rack2->id,
        'device_type_id' => $deviceType->id,
    ]);

    // Export only devices from datacenter1
    $export = new DeviceExport(['datacenter_id' => $datacenter1->id]);
    Excel::store($export, 'devices_export.xlsx', 'local');

    $filePath = Storage::disk('local')->path('devices_export.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Count data rows (excluding header)
    $rowCount = 0;
    $row = 2;
    while ($sheet->getCellByColumnAndRow(1, $row)->getValue() !== null) {
        $rowCount++;
        $row++;
    }

    expect($rowCount)->toBe(3);
});

test('export headings match corresponding template export headings', function () {
    // Verify all export classes have matching headings with their templates
    $exportClasses = [
        DatacenterExport::class => DatacenterTemplateExport::class,
        RoomExport::class => RoomTemplateExport::class,
        RowExport::class => RowTemplateExport::class,
        RackExport::class => RackTemplateExport::class,
        DeviceExport::class => DeviceTemplateExport::class,
        PortExport::class => PortTemplateExport::class,
    ];

    foreach ($exportClasses as $exportClass => $templateClass) {
        $export = new $exportClass();
        $template = new $templateClass();

        expect($export->headings())->toBe(
            $template->headings(),
            "Headings for {$exportClass} should match {$templateClass}"
        );
    }
});

test('xlsx export has header styling applied', function () {
    Datacenter::factory()->count(2)->create();

    $export = new DatacenterExport();
    Excel::store($export, 'styled_export.xlsx', 'local');

    $filePath = Storage::disk('local')->path('styled_export.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Check header cell styling (row 1)
    $headerStyle = $sheet->getStyle('A1');
    $font = $headerStyle->getFont();

    // Verify header is bold
    expect($font->getBold())->toBeTrue();

    // Verify fill color matches AbstractTemplateExport HEADER_BG_COLOR (4472C4)
    $fillColor = $headerStyle->getFill()->getStartColor()->getRGB();
    expect($fillColor)->toBe('4472C4');
});

test('data values are correctly formatted in export', function () {
    $datacenter = Datacenter::factory()->create([
        'name' => 'Formatted DC',
        'address_line_1' => '123 Test Street',
        'city' => 'San Francisco',
        'state_province' => 'CA',
        'postal_code' => '94102',
        'country' => 'USA',
        'primary_contact_name' => 'John Doe',
        'primary_contact_email' => 'john@example.com',
        'primary_contact_phone' => '+1-555-123-4567',
    ]);

    $room = Room::factory()->create([
        'datacenter_id' => $datacenter->id,
        'name' => 'Server Room A',
    ]);

    $export = new RoomExport();
    Excel::store($export, 'rooms_export.xlsx', 'local');

    $filePath = Storage::disk('local')->path('rooms_export.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Verify datacenter_name is the first column and contains the parent datacenter name
    $datacenterName = $sheet->getCellByColumnAndRow(1, 2)->getValue();
    expect($datacenterName)->toBe('Formatted DC');

    // Verify room name
    $roomName = $sheet->getCellByColumnAndRow(2, 2)->getValue();
    expect($roomName)->toBe('Server Room A');

    // Verify type is exported as label (not enum value)
    $typeValue = $sheet->getCellByColumnAndRow(3, 2)->getValue();
    expect($typeValue)->toBe($room->type->label());
});

test('port export includes device relationship data', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'Port Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $deviceType = DeviceType::factory()->create();
    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Test Server',
    ]);

    Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    $export = new PortExport();
    Excel::store($export, 'ports_export.xlsx', 'local');

    $filePath = Storage::disk('local')->path('ports_export.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Device name should be in first column for Port export
    $deviceName = $sheet->getCellByColumnAndRow(1, 2)->getValue();
    expect($deviceName)->toBe('Test Server');

    // Port label should be in second column
    $portLabel = $sheet->getCellByColumnAndRow(2, 2)->getValue();
    expect($portLabel)->toBe('eth0');
});

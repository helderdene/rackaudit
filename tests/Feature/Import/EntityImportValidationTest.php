<?php

use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\RackUHeight;
use App\Enums\RoomType;
use App\Imports\DatacenterImport;
use App\Imports\DeviceImport;
use App\Imports\PortImport;
use App\Imports\RoomImport;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

test('datacenter import validates and creates datacenter with valid data', function () {
    $import = new DatacenterImport;

    $row = [
        'name' => 'Test Datacenter',
        'address_line_1' => '123 Main Street',
        'address_line_2' => 'Suite 100',
        'city' => 'New York',
        'state_province' => 'NY',
        'postal_code' => '10001',
        'country' => 'United States',
        'company_name' => 'Test Company',
        'primary_contact_name' => 'John Doe',
        'primary_contact_email' => 'john@example.com',
        'primary_contact_phone' => '555-123-4567',
    ];

    $result = $import->processRow($row, 1);

    expect($result['success'])->toBeTrue();
    expect($result['entity'])->toBeInstanceOf(Datacenter::class);
    expect($result['entity']->name)->toBe('Test Datacenter');
    expect($result['entity']->city)->toBe('New York');
});

test('room import creates room with valid parent datacenter reference', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);

    $import = new RoomImport;

    $row = [
        'datacenter_name' => 'Main DC',
        'name' => 'Server Room 1',
        'type' => 'Server Room',
        'description' => 'Main server room',
        'square_footage' => '5000',
    ];

    $result = $import->processRow($row, 1);

    expect($result['success'])->toBeTrue();
    expect($result['entity'])->toBeInstanceOf(Room::class);
    expect($result['entity']->name)->toBe('Server Room 1');
    expect($result['entity']->datacenter_id)->toBe($datacenter->id);
    expect($result['entity']->type)->toBe(RoomType::ServerRoom);
});

test('device import validates rack placement against rack capacity', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'DC1']);
    $room = Room::factory()->create(['name' => 'Room 1', 'datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['name' => 'Row A', 'room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'name' => 'Rack 1',
        'row_id' => $row->id,
        'u_height' => RackUHeight::U42,
    ]);
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    $import = new DeviceImport;

    // Test valid placement
    $validRow = [
        'datacenter_name' => 'DC1',
        'room_name' => 'Room 1',
        'row_name' => 'Row A',
        'rack_name' => 'Rack 1',
        'name' => 'Server 01',
        'device_type_name' => 'Server',
        'lifecycle_status' => 'In Stock',
        'u_height' => '4',
        'depth' => 'Standard',
        'width_type' => 'Full Width',
        'rack_face' => 'Front',
        'start_u' => '10',
    ];

    $result = $import->processRow($validRow, 1);
    expect($result['success'])->toBeTrue();
    expect($result['entity']->start_u)->toBe(10);

    // Test invalid placement (start_u + u_height > rack capacity)
    $invalidRow = [
        'datacenter_name' => 'DC1',
        'room_name' => 'Room 1',
        'row_name' => 'Row A',
        'rack_name' => 'Rack 1',
        'name' => 'Server 02',
        'device_type_name' => 'Server',
        'lifecycle_status' => 'In Stock',
        'u_height' => '4',
        'depth' => 'Standard',
        'width_type' => 'Full Width',
        'rack_face' => 'Front',
        'start_u' => '40', // 40 + 4 = 44 > 42
    ];

    $result = $import->processRow($invalidRow, 2);
    expect($result['success'])->toBeFalse();
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['field_name'])->toBe('start_u');
});

test('port import validates type and subtype compatibility', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'DC1']);
    $room = Room::factory()->create(['name' => 'Room 1', 'datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['name' => 'Row A', 'room_id' => $room->id]);
    $rack = Rack::factory()->create(['name' => 'Rack 1', 'row_id' => $row->id]);
    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);
    $device = Device::factory()->create([
        'name' => 'Switch 01',
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
    ]);

    $import = new PortImport;

    // Test valid Ethernet port with valid subtype
    $validRow = [
        'datacenter_name' => 'DC1',
        'room_name' => 'Room 1',
        'row_name' => 'Row A',
        'rack_name' => 'Rack 1',
        'device_name' => 'Switch 01',
        'label' => 'eth0',
        'type' => 'Ethernet',
        'subtype' => '1GbE',
        'status' => 'Available',
        'direction' => 'Uplink',
    ];

    $result = $import->processRow($validRow, 1);
    expect($result['success'])->toBeTrue();
    expect($result['entity']->type)->toBe(PortType::Ethernet);
    expect($result['entity']->subtype)->toBe(PortSubtype::Gbe1);

    // Test invalid subtype for port type (Power subtype on Ethernet port)
    $invalidRow = [
        'datacenter_name' => 'DC1',
        'room_name' => 'Room 1',
        'row_name' => 'Row A',
        'rack_name' => 'Rack 1',
        'device_name' => 'Switch 01',
        'label' => 'eth1',
        'type' => 'Ethernet',
        'subtype' => 'C13', // C13 is a power subtype, not ethernet
        'status' => 'Available',
    ];

    $result = $import->processRow($invalidRow, 2);
    expect($result['success'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
    $fieldNames = collect($result['errors'])->pluck('field_name')->toArray();
    expect($fieldNames)->toContain('subtype');
});

test('validation failure generates proper error format with row number and field name', function () {
    $import = new DatacenterImport;

    // Missing required fields
    $row = [
        'name' => '', // Empty name
        'address_line_1' => '123 Main Street',
        'city' => 'New York',
        'state_province' => 'NY',
        'postal_code' => '10001',
        'country' => 'United States',
        'primary_contact_name' => 'John Doe',
        'primary_contact_email' => 'invalid-email', // Invalid email
        'primary_contact_phone' => '555-123-4567',
    ];

    $result = $import->processRow($row, 5);

    expect($result['success'])->toBeFalse();
    expect($result['errors'])->toBeArray();
    expect(count($result['errors']))->toBeGreaterThanOrEqual(2);

    // Check error format
    foreach ($result['errors'] as $error) {
        expect($error)->toHaveKeys(['row_number', 'field_name', 'error_message']);
        expect($error['row_number'])->toBe(5);
        expect($error['field_name'])->toBeString();
        expect($error['error_message'])->toBeString();
    }
});

test('parent entity lookup by name path fails for non-existent parent', function () {
    // Create datacenter but not room
    Datacenter::factory()->create(['name' => 'Existing DC']);

    $import = new RoomImport;

    $row = [
        'datacenter_name' => 'Non-Existent DC',
        'name' => 'Server Room 1',
        'type' => 'Server Room',
    ];

    $result = $import->processRow($row, 1);

    expect($result['success'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
    $fieldNames = collect($result['errors'])->pluck('field_name')->toArray();
    expect($fieldNames)->toContain('datacenter_name');
});

test('enum validation rejects invalid enum values', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'DC1']);

    $import = new RoomImport;

    // Invalid room type
    $row = [
        'datacenter_name' => 'DC1',
        'name' => 'Test Room',
        'type' => 'Invalid Type', // Not a valid RoomType
    ];

    $result = $import->processRow($row, 1);

    expect($result['success'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
    $fieldNames = collect($result['errors'])->pluck('field_name')->toArray();
    expect($fieldNames)->toContain('type');
});

test('row level error collection does not stop import processing', function () {
    $import = new DatacenterImport;
    $errors = new Collection;

    // First row - valid
    $validRow = [
        'name' => 'Valid DC',
        'address_line_1' => '123 Main Street',
        'city' => 'New York',
        'state_province' => 'NY',
        'postal_code' => '10001',
        'country' => 'United States',
        'primary_contact_name' => 'John Doe',
        'primary_contact_email' => 'john@example.com',
        'primary_contact_phone' => '555-123-4567',
    ];

    // Second row - invalid
    $invalidRow = [
        'name' => '',
        'address_line_1' => '456 Other Street',
        'city' => 'Boston',
        'state_province' => 'MA',
        'postal_code' => '02101',
        'country' => 'United States',
        'primary_contact_name' => 'Jane Doe',
        'primary_contact_email' => 'jane@example.com',
        'primary_contact_phone' => '555-987-6543',
    ];

    // Third row - valid
    $validRow2 = [
        'name' => 'Another Valid DC',
        'address_line_1' => '789 Third Street',
        'city' => 'Chicago',
        'state_province' => 'IL',
        'postal_code' => '60601',
        'country' => 'United States',
        'primary_contact_name' => 'Bob Smith',
        'primary_contact_email' => 'bob@example.com',
        'primary_contact_phone' => '555-555-5555',
    ];

    $result1 = $import->processRow($validRow, 1);
    $result2 = $import->processRow($invalidRow, 2);
    $result3 = $import->processRow($validRow2, 3);

    // First row should succeed
    expect($result1['success'])->toBeTrue();
    expect($result1['entity'])->toBeInstanceOf(Datacenter::class);

    // Second row should fail but not throw exception
    expect($result2['success'])->toBeFalse();
    expect($result2['errors'])->not->toBeEmpty();

    // Third row should still be processed and succeed
    expect($result3['success'])->toBeTrue();
    expect($result3['entity'])->toBeInstanceOf(Datacenter::class);
    expect($result3['entity']->name)->toBe('Another Valid DC');
});

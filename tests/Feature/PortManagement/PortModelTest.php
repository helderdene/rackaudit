<?php

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\PortVisualFace;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Port belongs to Device relationship', function () {
    $device = Device::factory()->create();

    $port = Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    expect($port->device)->toBeInstanceOf(Device::class);
    expect($port->device->id)->toBe($device->id);
});

test('PortType and PortSubtype enum casts work correctly', function () {
    $device = Device::factory()->create();

    $port = Port::create([
        'device_id' => $device->id,
        'label' => 'eth0',
        'type' => PortType::Ethernet,
        'subtype' => PortSubtype::Gbe10,
        'status' => PortStatus::Available,
        'direction' => PortDirection::Bidirectional,
    ]);

    // Retrieve fresh from database
    $freshPort = Port::find($port->id);

    expect($freshPort->type)->toBeInstanceOf(PortType::class);
    expect($freshPort->type)->toBe(PortType::Ethernet);
    expect($freshPort->type->label())->toBe('Ethernet');

    expect($freshPort->subtype)->toBeInstanceOf(PortSubtype::class);
    expect($freshPort->subtype)->toBe(PortSubtype::Gbe10);
    expect($freshPort->subtype->label())->toBe('10GbE');
});

test('PortStatus enum cast and default value', function () {
    $device = Device::factory()->create();

    $port = Port::create([
        'device_id' => $device->id,
        'label' => 'eth1',
        'type' => PortType::Ethernet,
        'subtype' => PortSubtype::Gbe1,
        'direction' => PortDirection::Bidirectional,
    ]);

    // Retrieve fresh from database
    $freshPort = Port::find($port->id);

    expect($freshPort->status)->toBeInstanceOf(PortStatus::class);
    expect($freshPort->status)->toBe(PortStatus::Available);
    expect($freshPort->status->label())->toBe('Available');
});

test('PortDirection enum cast with type-appropriate defaults', function () {
    // Test network port directions
    $networkDirections = PortDirection::forType(PortType::Ethernet);
    expect($networkDirections)->toContain(PortDirection::Uplink);
    expect($networkDirections)->toContain(PortDirection::Downlink);
    expect($networkDirections)->toContain(PortDirection::Bidirectional);
    expect($networkDirections)->not->toContain(PortDirection::Input);
    expect($networkDirections)->not->toContain(PortDirection::Output);

    // Test power port directions
    $powerDirections = PortDirection::forType(PortType::Power);
    expect($powerDirections)->toContain(PortDirection::Input);
    expect($powerDirections)->toContain(PortDirection::Output);
    expect($powerDirections)->not->toContain(PortDirection::Uplink);
    expect($powerDirections)->not->toContain(PortDirection::Downlink);

    // Test default directions
    expect(PortDirection::defaultForType(PortType::Ethernet))->toBe(PortDirection::Bidirectional);
    expect(PortDirection::defaultForType(PortType::Fiber))->toBe(PortDirection::Bidirectional);
    expect(PortDirection::defaultForType(PortType::Power))->toBe(PortDirection::Input);
});

test('subtype validation matches parent type (Ethernet subtypes only with Ethernet type)', function () {
    // Ethernet subtypes
    $ethernetSubtypes = PortSubtype::forType(PortType::Ethernet);
    expect($ethernetSubtypes)->toContain(PortSubtype::Gbe1);
    expect($ethernetSubtypes)->toContain(PortSubtype::Gbe10);
    expect($ethernetSubtypes)->toContain(PortSubtype::Gbe25);
    expect($ethernetSubtypes)->toContain(PortSubtype::Gbe40);
    expect($ethernetSubtypes)->toContain(PortSubtype::Gbe100);
    expect($ethernetSubtypes)->not->toContain(PortSubtype::Lc);
    expect($ethernetSubtypes)->not->toContain(PortSubtype::C13);

    // Fiber subtypes
    $fiberSubtypes = PortSubtype::forType(PortType::Fiber);
    expect($fiberSubtypes)->toContain(PortSubtype::Lc);
    expect($fiberSubtypes)->toContain(PortSubtype::Sc);
    expect($fiberSubtypes)->toContain(PortSubtype::Mpo);
    expect($fiberSubtypes)->not->toContain(PortSubtype::Gbe10);
    expect($fiberSubtypes)->not->toContain(PortSubtype::C13);

    // Power subtypes
    $powerSubtypes = PortSubtype::forType(PortType::Power);
    expect($powerSubtypes)->toContain(PortSubtype::C13);
    expect($powerSubtypes)->toContain(PortSubtype::C14);
    expect($powerSubtypes)->toContain(PortSubtype::C19);
    expect($powerSubtypes)->toContain(PortSubtype::C20);
    expect($powerSubtypes)->not->toContain(PortSubtype::Gbe10);
    expect($powerSubtypes)->not->toContain(PortSubtype::Lc);
});

test('cascade delete when parent Device is deleted', function () {
    $device = Device::factory()->create();

    $port1 = Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);
    $port2 = Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth1',
    ]);

    expect(Port::where('device_id', $device->id)->count())->toBe(2);

    // Delete the device
    $device->delete();

    // Ports should be cascade deleted
    expect(Port::where('device_id', $device->id)->count())->toBe(0);
    expect(Port::find($port1->id))->toBeNull();
    expect(Port::find($port2->id))->toBeNull();
});

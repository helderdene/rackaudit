<?php

use App\Enums\CableType;
use App\Enums\PortType;

test('CableType enum has all required values', function () {
    $expectedValues = [
        'cat5e',
        'cat6',
        'cat6a',
        'fiber_sm',
        'fiber_mm',
        'power_c13',
        'power_c14',
        'power_c19',
        'power_c20',
    ];

    $actualValues = array_map(fn (CableType $type) => $type->value, CableType::cases());

    expect($actualValues)->toBe($expectedValues);
});

test('CableType label method returns correct human-readable names', function () {
    expect(CableType::Cat5e->label())->toBe('Cat5e');
    expect(CableType::Cat6->label())->toBe('Cat6');
    expect(CableType::Cat6a->label())->toBe('Cat6a');
    expect(CableType::FiberSm->label())->toBe('Fiber SM');
    expect(CableType::FiberMm->label())->toBe('Fiber MM');
    expect(CableType::PowerC13->label())->toBe('C13');
    expect(CableType::PowerC14->label())->toBe('C14');
    expect(CableType::PowerC19->label())->toBe('C19');
    expect(CableType::PowerC20->label())->toBe('C20');
});

test('CableType forPortType returns correct cable types for Ethernet', function () {
    $ethernetCables = CableType::forPortType(PortType::Ethernet);

    expect($ethernetCables)->toContain(CableType::Cat5e);
    expect($ethernetCables)->toContain(CableType::Cat6);
    expect($ethernetCables)->toContain(CableType::Cat6a);
    expect($ethernetCables)->not->toContain(CableType::FiberSm);
    expect($ethernetCables)->not->toContain(CableType::FiberMm);
    expect($ethernetCables)->not->toContain(CableType::PowerC13);
});

test('CableType forPortType returns correct cable types for Fiber', function () {
    $fiberCables = CableType::forPortType(PortType::Fiber);

    expect($fiberCables)->toContain(CableType::FiberSm);
    expect($fiberCables)->toContain(CableType::FiberMm);
    expect($fiberCables)->not->toContain(CableType::Cat5e);
    expect($fiberCables)->not->toContain(CableType::Cat6);
    expect($fiberCables)->not->toContain(CableType::PowerC13);
});

test('CableType forPortType returns correct cable types for Power', function () {
    $powerCables = CableType::forPortType(PortType::Power);

    expect($powerCables)->toContain(CableType::PowerC13);
    expect($powerCables)->toContain(CableType::PowerC14);
    expect($powerCables)->toContain(CableType::PowerC19);
    expect($powerCables)->toContain(CableType::PowerC20);
    expect($powerCables)->not->toContain(CableType::Cat5e);
    expect($powerCables)->not->toContain(CableType::FiberSm);
});

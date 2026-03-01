<?php

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\ActivityLog;
use App\Models\Datacenter;
use App\Models\Pdu;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;

test('rack model can be created with valid data', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    $rack = Rack::factory()->create([
        'name' => 'Rack A1',
        'position' => 1,
        'u_height' => RackUHeight::U42,
        'serial_number' => 'SN-12345',
        'status' => RackStatus::Active,
        'row_id' => $row->id,
    ]);

    expect($rack)->toBeInstanceOf(Rack::class);
    expect($rack->name)->toBe('Rack A1');
    expect($rack->position)->toBe(1);
    expect($rack->serial_number)->toBe('SN-12345');
    expect($rack->row_id)->toBe($row->id);
});

test('rack status enum casting and label method work correctly', function () {
    $rack = Rack::factory()->create(['status' => RackStatus::Active]);
    $freshRack = Rack::find($rack->id);

    expect($freshRack->status)->toBeInstanceOf(RackStatus::class);
    expect($freshRack->status)->toBe(RackStatus::Active);
    expect($freshRack->status->value)->toBe('active');
    expect($freshRack->status->label())->toBe('Active');

    // Test all status labels
    expect(RackStatus::Active->label())->toBe('Active');
    expect(RackStatus::Inactive->label())->toBe('Inactive');
    expect(RackStatus::Maintenance->label())->toBe('Maintenance');
});

test('rack u_height enum casting and label method work correctly', function () {
    $rack42 = Rack::factory()->create(['u_height' => RackUHeight::U42]);
    $rack45 = Rack::factory()->create(['u_height' => RackUHeight::U45]);
    $rack48 = Rack::factory()->create(['u_height' => RackUHeight::U48]);

    // Test casting
    $freshRack = Rack::find($rack42->id);
    expect($freshRack->u_height)->toBeInstanceOf(RackUHeight::class);
    expect($freshRack->u_height)->toBe(RackUHeight::U42);
    expect($freshRack->u_height->value)->toBe(42);

    // Test all u_height labels
    expect(RackUHeight::U42->label())->toBe('42U');
    expect(RackUHeight::U45->label())->toBe('45U');
    expect(RackUHeight::U48->label())->toBe('48U');
});

test('rack belongs to row relationship', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    expect($rack->row)->toBeInstanceOf(Row::class);
    expect($rack->row->id)->toBe($row->id);

    // Verify Row has many racks relationship works
    $racks = $row->racks;
    expect($racks)->toHaveCount(1);
    expect($racks->first()->id)->toBe($rack->id);
});

test('rack many-to-many pdu relationship', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create PDUs - one room-level and one row-level
    $pdu1 = Pdu::factory()->create(['room_id' => $room->id, 'row_id' => null]);
    $pdu2 = Pdu::factory()->create(['room_id' => null, 'row_id' => $row->id]);

    // Attach PDUs to rack
    $rack->pdus()->attach([$pdu1->id, $pdu2->id]);

    // Verify rack has PDUs
    $rackPdus = $rack->pdus;
    expect($rackPdus)->toHaveCount(2);
    expect($rackPdus->pluck('id')->toArray())->toContain($pdu1->id);
    expect($rackPdus->pluck('id')->toArray())->toContain($pdu2->id);

    // Verify PDU has racks relationship
    $pdu1Racks = $pdu1->racks;
    expect($pdu1Racks)->toHaveCount(1);
    expect($pdu1Racks->first()->id)->toBe($rack->id);

    // Verify pivot timestamps exist
    expect($rack->pdus()->first()->pivot->created_at)->not->toBeNull();
});

test('rack loggable concern creates activity log on create', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Clear any existing activity logs
    ActivityLog::query()->delete();

    $rack = Rack::factory()->create([
        'name' => 'Rack Log Test',
        'row_id' => $row->id,
    ]);

    // Find the activity log for the rack creation
    $log = ActivityLog::where('subject_type', Rack::class)
        ->where('subject_id', $rack->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->action)->toBe('created');
    expect($log->new_values)->toHaveKey('name');
    expect($log->new_values['name'])->toBe('Rack Log Test');
});

<?php

use App\Enums\PduPhase;
use App\Enums\PduStatus;
use App\Enums\RoomType;
use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Models\Datacenter;
use App\Models\Pdu;
use App\Models\Room;
use App\Models\Row;

test('room belongs to datacenter relationship', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    expect($room->datacenter)->toBeInstanceOf(Datacenter::class);
    expect($room->datacenter->id)->toBe($datacenter->id);
});

test('room type enum casting works correctly', function () {
    $room = Room::factory()->create(['type' => RoomType::ServerRoom]);

    $freshRoom = Room::find($room->id);

    expect($freshRoom->type)->toBeInstanceOf(RoomType::class);
    expect($freshRoom->type)->toBe(RoomType::ServerRoom);
    expect($freshRoom->type->value)->toBe('server_room');
});

test('row belongs to room relationship', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    expect($row->room)->toBeInstanceOf(Room::class);
    expect($row->room->id)->toBe($room->id);
});

test('row position ordering within room', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    $row1 = Row::factory()->create(['room_id' => $room->id, 'position' => 1]);
    $row2 = Row::factory()->create(['room_id' => $room->id, 'position' => 2]);
    $row3 = Row::factory()->create(['room_id' => $room->id, 'position' => 3]);

    $orderedRows = $room->rows()->orderBy('position')->get();

    expect($orderedRows)->toHaveCount(3);
    expect($orderedRows[0]->id)->toBe($row1->id);
    expect($orderedRows[1]->id)->toBe($row2->id);
    expect($orderedRows[2]->id)->toBe($row3->id);
});

test('pdu polymorphic assignment room-level vs row-level', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Room-level PDU
    $roomLevelPdu = Pdu::factory()->create([
        'room_id' => $room->id,
        'row_id' => null,
    ]);

    // Row-level PDU
    $rowLevelPdu = Pdu::factory()->create([
        'room_id' => null,
        'row_id' => $row->id,
    ]);

    // Verify room-level PDU
    expect($roomLevelPdu->room)->toBeInstanceOf(Room::class);
    expect($roomLevelPdu->room->id)->toBe($room->id);
    expect($roomLevelPdu->row)->toBeNull();

    // Verify row-level PDU
    expect($rowLevelPdu->row)->toBeInstanceOf(Row::class);
    expect($rowLevelPdu->row->id)->toBe($row->id);
    expect($rowLevelPdu->room)->toBeNull();

    // Verify relationships work from parent
    expect($room->pdus)->toHaveCount(1);
    expect($row->pdus)->toHaveCount(1);
});

test('enum values return correct labels', function () {
    // RoomType labels
    expect(RoomType::ServerRoom->label())->toBe('Server Room');
    expect(RoomType::NetworkCloset->label())->toBe('Network Closet');
    expect(RoomType::CageColocation->label())->toBe('Cage/Colocation Space');
    expect(RoomType::Storage->label())->toBe('Storage');
    expect(RoomType::ElectricalRoom->label())->toBe('Electrical Room');

    // RowOrientation labels
    expect(RowOrientation::HotAisle->label())->toBe('Hot Aisle');
    expect(RowOrientation::ColdAisle->label())->toBe('Cold Aisle');

    // RowStatus labels
    expect(RowStatus::Active->label())->toBe('Active');
    expect(RowStatus::Inactive->label())->toBe('Inactive');

    // PduPhase labels
    expect(PduPhase::Single->label())->toBe('Single Phase');
    expect(PduPhase::ThreePhase->label())->toBe('Three Phase');

    // PduStatus labels
    expect(PduStatus::Active->label())->toBe('Active');
    expect(PduStatus::Inactive->label())->toBe('Inactive');
    expect(PduStatus::Maintenance->label())->toBe('Maintenance');
});

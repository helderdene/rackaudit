<?php

use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audit can be created with all required fields via factory', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create();

    $audit = Audit::factory()->create([
        'name' => 'Quarterly Connection Audit',
        'description' => 'Q1 2025 audit for primary datacenter',
        'due_date' => now()->addDays(30),
        'type' => AuditType::Connection,
        'scope_type' => AuditScopeType::Datacenter,
        'status' => AuditStatus::Pending,
        'datacenter_id' => $datacenter->id,
        'created_by' => $user->id,
    ]);

    expect($audit)->toBeInstanceOf(Audit::class);
    expect($audit->id)->not->toBeNull();
    expect($audit->name)->toBe('Quarterly Connection Audit');
    expect($audit->description)->toBe('Q1 2025 audit for primary datacenter');
    expect($audit->type)->toBe(AuditType::Connection);
    expect($audit->scope_type)->toBe(AuditScopeType::Datacenter);
    expect($audit->status)->toBe(AuditStatus::Pending);
    expect($audit->datacenter_id)->toBe($datacenter->id);
    expect($audit->created_by)->toBe($user->id);
});

test('audit belongs to datacenter', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'Main Datacenter']);

    $audit = Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    expect($audit->datacenter)->toBeInstanceOf(Datacenter::class);
    expect($audit->datacenter->id)->toBe($datacenter->id);
    expect($audit->datacenter->name)->toBe('Main Datacenter');
});

test('audit can have multiple assignees via pivot', function () {
    $audit = Audit::factory()->create();

    $assignee1 = User::factory()->create(['name' => 'Alice Johnson']);
    $assignee2 = User::factory()->create(['name' => 'Bob Smith']);
    $assignee3 = User::factory()->create(['name' => 'Carol Williams']);

    $audit->assignees()->attach([$assignee1->id, $assignee2->id, $assignee3->id]);

    $audit->refresh();

    expect($audit->assignees)->toHaveCount(3);
    expect($audit->assignees->pluck('id')->toArray())
        ->toContain($assignee1->id, $assignee2->id, $assignee3->id);
    expect($audit->assignees->pluck('name')->toArray())
        ->toContain('Alice Johnson', 'Bob Smith', 'Carol Williams');

    // Verify pivot table timestamps exist
    expect($audit->assignees->first()->pivot->created_at)->not->toBeNull();
});

test('audit can have multiple racks via pivot', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    $rack1 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack A1']);
    $rack2 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack A2']);
    $rack3 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack A3']);

    $audit = Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
        'scope_type' => AuditScopeType::Racks,
    ]);

    $audit->racks()->attach([$rack1->id, $rack2->id, $rack3->id]);

    $audit->refresh();

    expect($audit->racks)->toHaveCount(3);
    expect($audit->racks->pluck('id')->toArray())
        ->toContain($rack1->id, $rack2->id, $rack3->id);
    expect($audit->racks->pluck('name')->toArray())
        ->toContain('Rack A1', 'Rack A2', 'Rack A3');

    // Verify pivot table timestamps exist
    expect($audit->racks->first()->pivot->created_at)->not->toBeNull();
});

test('audit can have multiple devices via pivot for optional scope', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $device1 = Device::factory()->placed($rack, 1)->create(['name' => 'Server 01']);
    $device2 = Device::factory()->placed($rack, 5)->create(['name' => 'Server 02']);
    $device3 = Device::factory()->placed($rack, 10)->create(['name' => 'Server 03']);

    $audit = Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
        'scope_type' => AuditScopeType::Racks,
    ]);

    // Attach specific devices (optional scope within racks)
    $audit->devices()->attach([$device1->id, $device2->id, $device3->id]);

    $audit->refresh();

    expect($audit->devices)->toHaveCount(3);
    expect($audit->devices->pluck('id')->toArray())
        ->toContain($device1->id, $device2->id, $device3->id);
    expect($audit->devices->pluck('name')->toArray())
        ->toContain('Server 01', 'Server 02', 'Server 03');

    // Verify pivot table timestamps exist
    expect($audit->devices->first()->pivot->created_at)->not->toBeNull();
});

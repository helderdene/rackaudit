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

test('audit can be created with required fields', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create();

    $audit = Audit::factory()->create([
        'name' => 'Q1 2025 Connection Audit',
        'description' => 'Quarterly connection verification audit',
        'due_date' => now()->addDays(14),
        'type' => AuditType::Connection,
        'scope_type' => AuditScopeType::Datacenter,
        'status' => AuditStatus::Pending,
        'datacenter_id' => $datacenter->id,
        'created_by' => $user->id,
    ]);

    expect($audit->name)->toBe('Q1 2025 Connection Audit');
    expect($audit->description)->toBe('Quarterly connection verification audit');
    expect($audit->due_date)->not->toBeNull();
    expect($audit->datacenter_id)->toBe($datacenter->id);
    expect($audit->created_by)->toBe($user->id);
});

test('audit type enum is cast correctly', function () {
    $connectionAudit = Audit::factory()->create([
        'type' => AuditType::Connection,
    ]);

    $inventoryAudit = Audit::factory()->create([
        'type' => AuditType::Inventory,
    ]);

    expect($connectionAudit->type)->toBe(AuditType::Connection);
    expect($connectionAudit->type)->toBeInstanceOf(AuditType::class);
    expect($connectionAudit->type->label())->toBe('Connection');

    expect($inventoryAudit->type)->toBe(AuditType::Inventory);
    expect($inventoryAudit->type)->toBeInstanceOf(AuditType::class);
    expect($inventoryAudit->type->label())->toBe('Inventory');
});

test('audit scope type enum is cast correctly', function () {
    $datacenterScope = Audit::factory()->create([
        'scope_type' => AuditScopeType::Datacenter,
    ]);

    $roomScope = Audit::factory()->create([
        'scope_type' => AuditScopeType::Room,
    ]);

    $racksScope = Audit::factory()->create([
        'scope_type' => AuditScopeType::Racks,
    ]);

    expect($datacenterScope->scope_type)->toBe(AuditScopeType::Datacenter);
    expect($datacenterScope->scope_type)->toBeInstanceOf(AuditScopeType::class);
    expect($datacenterScope->scope_type->label())->toBe('Datacenter');

    expect($roomScope->scope_type)->toBe(AuditScopeType::Room);
    expect($roomScope->scope_type->label())->toBe('Room');

    expect($racksScope->scope_type)->toBe(AuditScopeType::Racks);
    expect($racksScope->scope_type->label())->toBe('Racks');
});

test('audit status defaults to pending on creation', function () {
    $audit = Audit::factory()->create();

    expect($audit->status)->toBe(AuditStatus::Pending);
    expect($audit->status)->toBeInstanceOf(AuditStatus::class);
    expect($audit->status->label())->toBe('Pending');

    // Verify other status values work correctly
    $inProgressAudit = Audit::factory()->create([
        'status' => AuditStatus::InProgress,
    ]);
    expect($inProgressAudit->status)->toBe(AuditStatus::InProgress);
    expect($inProgressAudit->status->label())->toBe('In Progress');

    $completedAudit = Audit::factory()->create([
        'status' => AuditStatus::Completed,
    ]);
    expect($completedAudit->status)->toBe(AuditStatus::Completed);
    expect($completedAudit->status->label())->toBe('Completed');

    $cancelledAudit = Audit::factory()->create([
        'status' => AuditStatus::Cancelled,
    ]);
    expect($cancelledAudit->status)->toBe(AuditStatus::Cancelled);
    expect($cancelledAudit->status->label())->toBe('Cancelled');
});

test('audit belongs to datacenter and room', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'Primary DC']);
    $room = Room::factory()->create([
        'name' => 'Server Room A',
        'datacenter_id' => $datacenter->id,
    ]);

    $audit = Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
        'room_id' => $room->id,
        'scope_type' => AuditScopeType::Room,
    ]);

    expect($audit->datacenter)->toBeInstanceOf(Datacenter::class);
    expect($audit->datacenter->id)->toBe($datacenter->id);
    expect($audit->datacenter->name)->toBe('Primary DC');

    expect($audit->room)->toBeInstanceOf(Room::class);
    expect($audit->room->id)->toBe($room->id);
    expect($audit->room->name)->toBe('Server Room A');
});

test('audit can have multiple assignees', function () {
    $audit = Audit::factory()->create();
    $user1 = User::factory()->create(['name' => 'John Doe']);
    $user2 = User::factory()->create(['name' => 'Jane Smith']);
    $user3 = User::factory()->create(['name' => 'Bob Wilson']);

    $audit->assignees()->attach([$user1->id, $user2->id, $user3->id]);

    expect($audit->assignees)->toHaveCount(3);
    expect($audit->assignees->pluck('id')->toArray())
        ->toContain($user1->id, $user2->id, $user3->id);
    expect($audit->assignees->pluck('name')->toArray())
        ->toContain('John Doe', 'Jane Smith', 'Bob Wilson');
});

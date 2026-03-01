<?php

use App\Models\Audit;
use App\Models\AuditReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audit report can be created with required fields', function () {
    $audit = Audit::factory()->create();
    $user = User::factory()->create();

    $auditReport = AuditReport::create([
        'audit_id' => $audit->id,
        'user_id' => $user->id,
        'file_path' => 'reports/audits/audit-report-1-20251229153045.pdf',
        'generated_at' => now(),
        'file_size_bytes' => 1024000,
    ]);

    expect($auditReport->audit_id)->toBe($audit->id);
    expect($auditReport->user_id)->toBe($user->id);
    expect($auditReport->file_path)->toBe('reports/audits/audit-report-1-20251229153045.pdf');
    expect($auditReport->generated_at)->not->toBeNull();
    expect($auditReport->file_size_bytes)->toBe(1024000);
});

test('audit report belongs to audit', function () {
    $audit = Audit::factory()->create(['name' => 'Q1 2025 Audit']);
    $auditReport = AuditReport::factory()->create(['audit_id' => $audit->id]);

    expect($auditReport->audit)->toBeInstanceOf(Audit::class);
    expect($auditReport->audit->id)->toBe($audit->id);
    expect($auditReport->audit->name)->toBe('Q1 2025 Audit');
});

test('audit report belongs to generator user', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $auditReport = AuditReport::factory()->create(['user_id' => $user->id]);

    expect($auditReport->generator)->toBeInstanceOf(User::class);
    expect($auditReport->generator->id)->toBe($user->id);
    expect($auditReport->generator->name)->toBe('John Doe');
});

test('audit report can be soft deleted', function () {
    $auditReport = AuditReport::factory()->create();

    expect(AuditReport::count())->toBe(1);

    $auditReport->delete();

    expect(AuditReport::count())->toBe(0);
    expect(AuditReport::withTrashed()->count())->toBe(1);
    expect($auditReport->fresh()->deleted_at)->not->toBeNull();
});

test('audit report casts generated_at to datetime', function () {
    $auditReport = AuditReport::factory()->create([
        'generated_at' => '2025-12-29 15:30:45',
    ]);

    expect($auditReport->generated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($auditReport->generated_at->format('Y-m-d H:i:s'))->toBe('2025-12-29 15:30:45');
});

test('audit report casts file_size_bytes to integer', function () {
    $auditReport = AuditReport::factory()->create([
        'file_size_bytes' => '2048000',
    ]);

    expect($auditReport->file_size_bytes)->toBeInt();
    expect($auditReport->file_size_bytes)->toBe(2048000);
});

test('audit has many reports relationship', function () {
    $audit = Audit::factory()->create();
    AuditReport::factory()->count(3)->create(['audit_id' => $audit->id]);

    expect($audit->reports)->toHaveCount(3);
    expect($audit->reports->first())->toBeInstanceOf(AuditReport::class);
});

test('audit reports are ordered by generated_at descending by default', function () {
    $audit = Audit::factory()->create();

    $oldReport = AuditReport::factory()->create([
        'audit_id' => $audit->id,
        'generated_at' => now()->subDays(2),
    ]);

    $newestReport = AuditReport::factory()->create([
        'audit_id' => $audit->id,
        'generated_at' => now(),
    ]);

    $middleReport = AuditReport::factory()->create([
        'audit_id' => $audit->id,
        'generated_at' => now()->subDay(),
    ]);

    $audit->refresh();

    expect($audit->reports->first()->id)->toBe($newestReport->id);
    expect($audit->reports->last()->id)->toBe($oldReport->id);
});

<?php

use App\Enums\DiscrepancyType;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('finding can be created with required fields', function () {
    $audit = Audit::factory()->create();
    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->discrepant()
        ->create();

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => $verification->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Missing connection at Rack A1',
        'description' => 'Expected connection between Switch-01 port 1 and Server-01 port 2 not found.',
    ]);

    expect($finding)->toBeInstanceOf(Finding::class);
    expect($finding->audit_id)->toBe($audit->id);
    expect($finding->audit_connection_verification_id)->toBe($verification->id);
    expect($finding->discrepancy_type)->toBe(DiscrepancyType::Missing);
    expect($finding->title)->toBe('Missing connection at Rack A1');
    expect($finding->description)->toBe('Expected connection between Switch-01 port 1 and Server-01 port 2 not found.');
});

test('finding belongs to audit connection verification', function () {
    $audit = Audit::factory()->create();
    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->discrepant()
        ->create();

    $finding = Finding::factory()
        ->forVerification($verification)
        ->create();

    expect($finding->verification)->toBeInstanceOf(AuditConnectionVerification::class);
    expect($finding->verification->id)->toBe($verification->id);
    expect($finding->audit)->toBeInstanceOf(Audit::class);
    expect($finding->audit->id)->toBe($audit->id);

    // Test inverse relationship
    expect($verification->fresh()->finding)->toBeInstanceOf(Finding::class);
    expect($verification->fresh()->finding->id)->toBe($finding->id);
});

test('finding status defaults to open', function () {
    $finding = Finding::factory()->create();

    expect($finding->status)->toBe(FindingStatus::Open);
    expect($finding->status)->toBeInstanceOf(FindingStatus::class);
    expect($finding->status->label())->toBe('Open');
    expect($finding->resolved_by)->toBeNull();
    expect($finding->resolved_at)->toBeNull();
});

test('finding can be resolved by a user', function () {
    $finding = Finding::factory()->create();
    $resolver = User::factory()->create(['name' => 'Jane Resolver']);

    expect($finding->status)->toBe(FindingStatus::Open);

    $finding->update([
        'status' => FindingStatus::Resolved,
        'resolved_by' => $resolver->id,
        'resolved_at' => now(),
    ]);

    $finding->refresh();

    expect($finding->status)->toBe(FindingStatus::Resolved);
    expect($finding->resolvedBy)->toBeInstanceOf(User::class);
    expect($finding->resolvedBy->name)->toBe('Jane Resolver');
    expect($finding->resolved_at)->not->toBeNull();
});

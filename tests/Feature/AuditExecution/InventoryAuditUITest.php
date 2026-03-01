<?php

use App\Enums\AuditScopeType;
use App\Enums\AuditType;
use App\Enums\DeviceVerificationStatus;
use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('inventory execute page renders with device list and progress stats', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
    ]);

    // Assign user to audit
    $audit->assignees()->attach($user);

    // Create some device verification items
    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->count(3)
        ->create();

    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->verified($user)
        ->count(2)
        ->create();

    $response = $this->actingAs($user)->get("/audits/{$audit->id}/inventory-execute");

    $response->assertSuccessful();
    $response->assertInertia(function ($page) use ($audit) {
        $page->component('Audits/InventoryExecute')
            ->where('audit.id', $audit->id)
            ->has('progress_stats')
            ->where('progress_stats.total', 5)
            ->where('progress_stats.verified', 2)
            ->where('progress_stats.pending', 3);
    });
});

test('device verification API returns devices grouped by rack with filters', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
    ]);

    // Create device verifications with different statuses
    $pending = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $verified = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->verified($user)
        ->create();

    // Fetch all verifications
    $response = $this->actingAs($user)->getJson("/api/audits/{$audit->id}/device-verifications");
    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');

    // Fetch only pending verifications
    $response = $this->actingAs($user)->getJson("/api/audits/{$audit->id}/device-verifications?verification_status=pending");
    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $pending->id);
});

test('bulk verify API processes only pending devices and skips locked', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
    ]);

    // Create pending verifications
    $pending1 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $pending2 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    // Create locked verification
    $locked = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->locked($otherUser)
        ->create();

    $response = $this->actingAs($user)->postJson("/api/audits/{$audit->id}/device-verifications/bulk-verify", [
        'verification_ids' => [$pending1->id, $pending2->id, $locked->id],
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.verified_count', 2);
    $response->assertJsonPath('results.skipped_locked_count', 1);

    // Verify database state
    expect($pending1->fresh()->verification_status)->toBe(DeviceVerificationStatus::Verified);
    expect($pending2->fresh()->verification_status)->toBe(DeviceVerificationStatus::Verified);
    expect($locked->fresh()->verification_status)->toBe(DeviceVerificationStatus::Pending);
});

test('marking device as not found creates finding automatically', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
    ]);

    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $response = $this->actingAs($user)->postJson(
        "/api/audits/{$audit->id}/device-verifications/{$verification->id}/not-found",
        ['notes' => 'Device not in documented location']
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.verification_status', 'not_found');
    $response->assertJsonPath('message', 'Device marked as not found. A finding has been created.');

    $verification->refresh();
    expect($verification->verification_status)->toBe(DeviceVerificationStatus::NotFound);
    expect($verification->finding)->not->toBeNull();
    expect($verification->finding->title)->toContain('Device not found');
});

test('show page displays inventory audit start and continue buttons', function () {
    $user = User::factory()->create();

    // Test pending inventory audit - should show Start Audit button
    $pendingAudit = Audit::factory()->pending()->create([
        'type' => AuditType::Inventory,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get("/audits/{$pendingAudit->id}");
    $response->assertInertia(function ($page) {
        $page->where('can_start_inventory_audit', true)
            ->where('can_continue_inventory_audit', false);
    });

    // Test in-progress inventory audit - should show Continue Audit button
    $inProgressAudit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get("/audits/{$inProgressAudit->id}");
    $response->assertInertia(function ($page) {
        $page->where('can_start_inventory_audit', false)
            ->where('can_continue_inventory_audit', true);
    });
});

test('device verification search filters by device name, asset tag, or serial number', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
    ]);

    // Create devices with searchable attributes
    $device1 = Device::factory()->create([
        'name' => 'Server-Rack-01',
        'asset_tag' => 'ASSET001',
        'serial_number' => 'SN123456',
    ]);

    $device2 = Device::factory()->create([
        'name' => 'Switch-Core-01',
        'asset_tag' => 'ASSET002',
        'serial_number' => 'SN789012',
    ]);

    // Create device verifications for those devices
    $verification1 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device1)
        ->pending()
        ->create();

    $verification2 = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device2)
        ->pending()
        ->create();

    // Search by device name
    $response = $this->actingAs($user)->getJson("/api/audits/{$audit->id}/device-verifications?search=Server");
    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $verification1->id);

    // Search by asset tag
    $response = $this->actingAs($user)->getJson("/api/audits/{$audit->id}/device-verifications?search=ASSET002");
    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $verification2->id);
});

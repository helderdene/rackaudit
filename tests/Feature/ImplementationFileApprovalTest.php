<?php

use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->datacenter = Datacenter::factory()->create();
});

/**
 * Test 1: Approval status field defaults to "pending_approval" on new records
 */
test('approval_status field defaults to pending_approval on new records', function () {
    $file = ImplementationFile::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'uploaded_by' => $this->admin->id,
    ]);

    expect($file->approval_status)->toBe('pending_approval');
    expect($file->approved_by)->toBeNull();
    expect($file->approved_at)->toBeNull();
});

/**
 * Test 2: Approved_by and approved_at are nullable and set correctly
 */
test('approved_by and approved_at are nullable and set correctly when approved', function () {
    $approver = User::factory()->create();
    $approver->assignRole('IT Manager');

    $file = ImplementationFile::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'uploaded_by' => $this->admin->id,
    ]);

    // Initially nullable
    expect($file->approved_by)->toBeNull();
    expect($file->approved_at)->toBeNull();

    // Simulate approval
    $approvalTime = now();
    $file->update([
        'approval_status' => 'approved',
        'approved_by' => $approver->id,
        'approved_at' => $approvalTime,
    ]);

    $file->refresh();

    expect($file->approval_status)->toBe('approved');
    expect($file->approved_by)->toBe($approver->id);
    expect($file->approved_at)->not->toBeNull();
    expect($file->approved_at->toDateTimeString())->toBe($approvalTime->toDateTimeString());
});

/**
 * Test 3: Approver relationship returns User model
 */
test('approver relationship returns User model', function () {
    $approver = User::factory()->create(['name' => 'Jane Approver']);
    $approver->assignRole('IT Manager');

    $file = ImplementationFile::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'uploaded_by' => $this->admin->id,
        'approval_status' => 'approved',
        'approved_by' => $approver->id,
        'approved_at' => now(),
    ]);

    expect($file->approver)->toBeInstanceOf(User::class);
    expect($file->approver->id)->toBe($approver->id);
    expect($file->approver->name)->toBe('Jane Approver');
});

/**
 * Test 4: New version inherits "pending_approval" status (approval does not carry over)
 */
test('new version inherits pending_approval status and approval does not carry over', function () {
    $approver = User::factory()->create();
    $approver->assignRole('IT Manager');

    // Create an approved file
    $originalFile = ImplementationFile::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'uploaded_by' => $this->admin->id,
        'original_name' => 'spec.pdf',
        'approval_status' => 'approved',
        'approved_by' => $approver->id,
        'approved_at' => now(),
        'version_number' => 1,
    ]);
    $originalFile->update(['version_group_id' => $originalFile->id]);

    // Create a new version (simulating upload of new version)
    $newVersion = ImplementationFile::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'uploaded_by' => $this->admin->id,
        'original_name' => 'spec.pdf',
        'version_group_id' => $originalFile->id,
        'version_number' => 2,
    ]);

    // New version should have pending_approval status, not inherit approval
    expect($newVersion->approval_status)->toBe('pending_approval');
    expect($newVersion->approved_by)->toBeNull();
    expect($newVersion->approved_at)->toBeNull();

    // Original file should still be approved
    $originalFile->refresh();
    expect($originalFile->approval_status)->toBe('approved');
    expect($originalFile->approved_by)->toBe($approver->id);
});

/**
 * Test 5: Casts for approval_status and approved_at datetime
 */
test('casts for approval_status enum and approved_at datetime work correctly', function () {
    $file = ImplementationFile::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'uploaded_by' => $this->admin->id,
        'approval_status' => 'approved',
        'approved_at' => '2025-01-15 10:30:00',
    ]);

    // approval_status should be a string (enum-like behavior)
    expect($file->approval_status)->toBeString();
    expect($file->approval_status)->toBe('approved');

    // approved_at should be cast to Carbon datetime
    expect($file->approved_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($file->approved_at->format('Y-m-d H:i:s'))->toBe('2025-01-15 10:30:00');
});

/**
 * Test 6: isPendingApproval and isApproved helper methods work correctly
 */
test('isPendingApproval and isApproved helper methods work correctly', function () {
    $pendingFile = ImplementationFile::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'uploaded_by' => $this->admin->id,
        'approval_status' => 'pending_approval',
    ]);

    $approvedFile = ImplementationFile::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'uploaded_by' => $this->admin->id,
        'approval_status' => 'approved',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    // Test pending file
    expect($pendingFile->isPendingApproval())->toBeTrue();
    expect($pendingFile->isApproved())->toBeFalse();

    // Test approved file
    expect($approvedFile->isPendingApproval())->toBeFalse();
    expect($approvedFile->isApproved())->toBeTrue();
});

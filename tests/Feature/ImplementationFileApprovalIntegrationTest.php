<?php

/**
 * Integration tests for the Implementation File Approval Workflow.
 *
 * These tests focus on end-to-end workflows and edge cases that span
 * multiple components of the approval system.
 */

use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use App\Notifications\ImplementationFileApprovedNotification;
use App\Notifications\ImplementationFileAwaitingApprovalNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Notification::fake();

    // Create users with different roles
    $this->admin = User::factory()->create(['name' => 'Admin User', 'status' => 'active']);
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create(['name' => 'IT Manager', 'status' => 'active']);
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create(['name' => 'Operator User', 'status' => 'active']);
    $this->operator->assignRole('Operator');

    // Create datacenter
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);

    // Give operator access to datacenter
    $this->operator->datacenters()->attach($this->datacenter);
});

/**
 * End-to-end Test 1: File upload triggers awaiting approval notification to approvers
 * Note: IT Manager uploads (since only Admin/IT Manager can upload)
 */
test('file upload triggers awaiting approval notification to all approvers', function () {
    $file = UploadedFile::fake()->create('implementation.pdf', 1024, 'application/pdf');

    // IT Manager uploads the file (triggers notification to Admin, not to self)
    $response = $this->actingAs($this->itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file,
            'description' => 'Test file description',
        ]);

    $response->assertStatus(201);

    // Admin should receive the awaiting approval notification
    // IT Manager (uploader) should not receive notification
    Notification::assertSentTo($this->admin, ImplementationFileAwaitingApprovalNotification::class);
    Notification::assertNotSentTo($this->itManager, ImplementationFileAwaitingApprovalNotification::class);
});

/**
 * End-to-end Test 2: File approval triggers approved notification to uploader
 */
test('file approval triggers approved notification to uploader', function () {
    // Create a pending file uploaded by IT Manager
    $file = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->itManager->id]);
    Storage::disk('local')->put($file->file_path, 'test content');

    // Approve as admin
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/approve");

    $response->assertOk();

    // IT Manager (uploader) should receive the approved notification
    Notification::assertSentTo($this->itManager, ImplementationFileApprovedNotification::class);
    // Admin (approver) should not receive notification
    Notification::assertNotSentTo($this->admin, ImplementationFileApprovedNotification::class);
});

/**
 * Edge case Test 3: Multiple approvers receive notification for same file
 */
test('all approvers with correct role receive notification for new file', function () {
    // Create additional admins and IT managers
    $admin2 = User::factory()->create(['name' => 'Admin User 2', 'status' => 'active']);
    $admin2->assignRole('Administrator');

    $itManager2 = User::factory()->create(['name' => 'IT Manager 2', 'status' => 'active']);
    $itManager2->assignRole('IT Manager');

    $file = UploadedFile::fake()->create('spec.pdf', 1024, 'application/pdf');

    // Admin uploads the file
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file,
        ]);

    $response->assertStatus(201);

    // All admins and IT managers (except the uploader) should receive notifications
    Notification::assertNotSentTo($this->admin, ImplementationFileAwaitingApprovalNotification::class); // Uploader
    Notification::assertSentTo($admin2, ImplementationFileAwaitingApprovalNotification::class);
    Notification::assertSentTo($this->itManager, ImplementationFileAwaitingApprovalNotification::class);
    Notification::assertSentTo($itManager2, ImplementationFileAwaitingApprovalNotification::class);
});

/**
 * Edge case Test 4: Version upload resets approval status
 */
test('uploading new version of approved file resets approval status', function () {
    // Create an approved file
    $originalFile = ImplementationFile::factory()
        ->approved($this->admin)
        ->for($this->datacenter)
        ->create([
            'uploaded_by' => $this->itManager->id,
            'original_name' => 'versioned-file.pdf',
            'version_number' => 1,
        ]);
    $originalFile->update(['version_group_id' => $originalFile->id]);
    Storage::disk('local')->put($originalFile->file_path, 'original content');

    // Upload new version with same original name (by IT Manager)
    $newFile = UploadedFile::fake()->create('versioned-file.pdf', 2048, 'application/pdf');

    $response = $this->actingAs($this->itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $newFile,
        ]);

    $response->assertStatus(201);

    // New version should be pending approval
    $newVersion = ImplementationFile::where('original_name', 'versioned-file.pdf')
        ->where('version_number', 2)
        ->first();

    expect($newVersion)->not->toBeNull();
    expect($newVersion->approval_status)->toBe('pending_approval');
    expect($newVersion->approved_by)->toBeNull();
    expect($newVersion->approved_at)->toBeNull();

    // Original should still be approved
    $originalFile->refresh();
    expect($originalFile->approval_status)->toBe('approved');
});

/**
 * Integration Test 5: Restored version also has pending approval status
 */
test('restored version has pending approval status regardless of original approval', function () {
    // Create an approved file as v1
    $originalFile = ImplementationFile::factory()
        ->approved($this->admin)
        ->for($this->datacenter)
        ->create([
            'uploaded_by' => $this->itManager->id,
            'original_name' => 'restore-test.pdf',
            'version_number' => 1,
        ]);
    $originalFile->update(['version_group_id' => $originalFile->id]);
    Storage::disk('local')->put($originalFile->file_path, 'original content');

    // Create v2 (approved)
    $v2 = ImplementationFile::factory()
        ->approved($this->itManager)
        ->for($this->datacenter)
        ->create([
            'uploaded_by' => $this->admin->id,
            'original_name' => 'restore-test.pdf',
            'version_group_id' => $originalFile->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($v2->file_path, 'v2 content');

    // Restore v1 (should create v3 with pending approval)
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$originalFile->id}/restore");

    $response->assertStatus(201);

    // Get the restored version (v3)
    $restoredVersion = ImplementationFile::where('version_group_id', $originalFile->version_group_id)
        ->where('version_number', 3)
        ->first();

    expect($restoredVersion)->not->toBeNull();
    expect($restoredVersion->approval_status)->toBe('pending_approval');
    expect($restoredVersion->approved_by)->toBeNull();
});

/**
 * Integration Test 6: Full approval workflow from upload to approval
 */
test('complete approval workflow from upload to approval', function () {
    // Step 1: IT Manager uploads a file
    $file = UploadedFile::fake()->create('complete-workflow.pdf', 1024, 'application/pdf');

    $uploadResponse = $this->actingAs($this->itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file,
            'description' => 'Complete workflow test',
        ]);

    $uploadResponse->assertStatus(201);
    $uploadedFile = ImplementationFile::where('original_name', 'complete-workflow.pdf')->first();

    // Verify file starts with pending approval
    expect($uploadedFile->approval_status)->toBe('pending_approval');

    // Verify notification was sent to admin (not the uploader IT Manager)
    Notification::assertSentTo($this->admin, ImplementationFileAwaitingApprovalNotification::class);
    Notification::assertNotSentTo($this->itManager, ImplementationFileAwaitingApprovalNotification::class);

    // Step 2: Check that datacenter shows no approved files
    expect($this->datacenter->hasApprovedImplementationFiles())->toBeFalse();

    // Step 3: Admin approves the file
    $approveResponse = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$uploadedFile->id}/approve");

    $approveResponse->assertOk();

    // Verify file is now approved
    $uploadedFile->refresh();
    expect($uploadedFile->approval_status)->toBe('approved');
    expect($uploadedFile->approved_by)->toBe($this->admin->id);
    expect($uploadedFile->approved_at)->not->toBeNull();

    // Verify notification was sent to uploader (IT Manager)
    Notification::assertSentTo($this->itManager, ImplementationFileApprovedNotification::class);

    // Step 4: Check that datacenter now shows approved files
    expect($this->datacenter->hasApprovedImplementationFiles())->toBeTrue();
});

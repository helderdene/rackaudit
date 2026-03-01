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

    // Create users with different roles
    $this->admin = User::factory()->create(['name' => 'Admin User']);
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create(['name' => 'IT Manager']);
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create(['name' => 'Operator User']);
    $this->operator->assignRole('Operator');

    // Create datacenter
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);

    // Give operator access to datacenter
    $this->operator->datacenters()->attach($this->datacenter);
});

/**
 * Test 1: Approval status badge displays correctly for pending and approved files
 * Tests the ImplementationFileResource returns correct approval_status for UI display
 */
test('approval status is returned in file resource for pending and approved files', function () {
    // Create a pending file
    $pendingFile = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($pendingFile->file_path, 'test content');

    // Create an approved file
    $approvedFile = ImplementationFile::factory()
        ->approved($this->itManager)
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($approvedFile->file_path, 'test content');

    // Fetch files as admin
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files");

    $response->assertOk();

    $data = $response->json('data');

    // Find the pending file in response
    $pendingInResponse = collect($data)->firstWhere('id', $pendingFile->id);
    expect($pendingInResponse)->not->toBeNull();
    expect($pendingInResponse['approval_status'])->toBe('pending_approval');
    expect($pendingInResponse['approved_at'])->toBeNull();
    expect($pendingInResponse['approver'])->toBeNull();

    // Find the approved file in response
    $approvedInResponse = collect($data)->firstWhere('id', $approvedFile->id);
    expect($approvedInResponse)->not->toBeNull();
    expect($approvedInResponse['approval_status'])->toBe('approved');
    expect($approvedInResponse['approved_at'])->not->toBeNull();
    expect($approvedInResponse['approver'])->not->toBeNull();
    expect($approvedInResponse['approver']['name'])->toBe('IT Manager');
});

/**
 * Test 2: can_approve permission is correctly calculated for different users
 * Tests that only authorized users see can_approve=true for pending files
 */
test('can_approve is true only for authorized users on pending files', function () {
    // Create a pending file uploaded by operator
    $pendingFile = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($pendingFile->file_path, 'test content');

    // Admin should be able to approve (not the uploader)
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$pendingFile->id}");
    $response->assertOk();
    expect($response->json('data.can_approve'))->toBeTrue();

    // IT Manager should be able to approve (not the uploader)
    $response = $this->actingAs($this->itManager)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$pendingFile->id}");
    $response->assertOk();
    expect($response->json('data.can_approve'))->toBeTrue();

    // Operator cannot approve (wrong role)
    $response = $this->actingAs($this->operator)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$pendingFile->id}");
    $response->assertOk();
    expect($response->json('data.can_approve'))->toBeFalse();
});

/**
 * Test 3: can_approve is false for files the user uploaded (separation of duties)
 * Tests that even admin users cannot approve their own uploads
 */
test('can_approve is false for files user uploaded regardless of role', function () {
    // Create a pending file uploaded by admin
    $adminFile = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id]);
    Storage::disk('local')->put($adminFile->file_path, 'test content');

    // Admin cannot approve their own file
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$adminFile->id}");
    $response->assertOk();
    expect($response->json('data.can_approve'))->toBeFalse();

    // But IT Manager can approve it
    $response = $this->actingAs($this->itManager)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$adminFile->id}");
    $response->assertOk();
    expect($response->json('data.can_approve'))->toBeTrue();
});

/**
 * Test 4: Approval filter returns correct files when filtering by status
 * Tests the API returns files that can be filtered client-side by approval_status
 */
test('files can be filtered by approval status on the response', function () {
    // Create multiple files with different statuses
    $pending1 = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($pending1->file_path, 'test content');

    $pending2 = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($pending2->file_path, 'test content');

    $approved1 = ImplementationFile::factory()
        ->approved($this->admin)
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($approved1->file_path, 'test content');

    // Fetch all files
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files");

    $response->assertOk();

    $data = collect($response->json('data'));

    // Verify we get all files
    expect($data)->toHaveCount(3);

    // Verify we can filter pending files
    $pendingFiles = $data->where('approval_status', 'pending_approval');
    expect($pendingFiles)->toHaveCount(2);

    // Verify we can filter approved files
    $approvedFiles = $data->where('approval_status', 'approved');
    expect($approvedFiles)->toHaveCount(1);
});

/**
 * Test 5: approve_url is included for pending files
 * Tests that the API provides the approve URL for pending files
 */
test('approve_url is included for pending files only', function () {
    // Create a pending file
    $pendingFile = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($pendingFile->file_path, 'test content');

    // Create an approved file
    $approvedFile = ImplementationFile::factory()
        ->approved($this->admin)
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($approvedFile->file_path, 'test content');

    // Check pending file has approve_url
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$pendingFile->id}");
    $response->assertOk();
    expect($response->json('data.approve_url'))->not->toBeNull();
    expect($response->json('data.approve_url'))->toContain('/approve');

    // Check approved file does NOT have approve_url
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$approvedFile->id}");
    $response->assertOk();
    expect($response->json('data'))->not->toHaveKey('approve_url');
});

/**
 * Test 6: Successful approval returns updated file with approver info
 * Tests the approval action returns the updated resource with approver details
 */
test('approval action returns updated file with approver info for UI', function () {
    // Create a pending file
    $pendingFile = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($pendingFile->file_path, 'test content');

    $this->freezeTime();

    // Approve the file as IT Manager
    $response = $this->actingAs($this->itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$pendingFile->id}/approve");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'approval_status',
                'approved_at',
                'approver' => ['id', 'name'],
            ],
            'message',
        ])
        ->assertJsonPath('data.approval_status', 'approved')
        ->assertJsonPath('data.approver.id', $this->itManager->id)
        ->assertJsonPath('data.approver.name', 'IT Manager')
        ->assertJsonPath('message', 'File approved successfully.');
});

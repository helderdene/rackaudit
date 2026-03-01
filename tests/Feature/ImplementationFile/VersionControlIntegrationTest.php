<?php

/**
 * Integration Tests for Version Control Feature
 *
 * These tests cover critical end-to-end workflows and edge cases for the
 * implementation file version control feature that are not covered by
 * individual component tests.
 */

use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    // Create users with different roles
    $this->admin = User::factory()->create(['name' => 'Admin User']);
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create(['name' => 'IT Manager User']);
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create(['name' => 'Operator User']);
    $this->operator->assignRole('Operator');

    $this->auditor = User::factory()->create(['name' => 'Auditor User']);
    $this->auditor->assignRole('Auditor');

    // Create a datacenter for testing
    $this->datacenter = Datacenter::factory()->create();

    // Assign datacenter access to non-admin users
    $this->operator->datacenters()->attach($this->datacenter);
    $this->auditor->datacenters()->attach($this->datacenter);
});

/**
 * End-to-end Test 1: Complete version lifecycle workflow
 * Upload file -> Upload new version -> View history -> Restore old version
 */
test('complete version lifecycle: upload, version, view history, restore', function () {
    // Step 1: Upload first file
    $file1 = UploadedFile::fake()->createWithContent('network_diagram.pdf', '%PDF-1.4 first version content');
    $response1 = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file1,
            'description' => 'Initial network diagram',
        ]);

    $response1->assertCreated();
    $version1Id = $response1->json('data.id');
    $version1 = ImplementationFile::find($version1Id);

    expect($version1->version_number)->toBe(1);
    expect($version1->version_group_id)->toBe($version1->id);

    // Step 2: Upload new version of same file
    $file2 = UploadedFile::fake()->createWithContent('network_diagram.pdf', '%PDF-1.4 second version content');
    $response2 = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file2,
            'description' => 'Updated network diagram',
        ]);

    $response2->assertCreated();
    $version2Id = $response2->json('data.id');
    $version2 = ImplementationFile::find($version2Id);

    expect($version2->version_number)->toBe(2);
    expect($version2->version_group_id)->toBe($version1->id);

    // Step 3: View version history
    $historyResponse = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1Id}/versions");

    $historyResponse->assertOk()
        ->assertJsonCount(2, 'data');

    $versions = $historyResponse->json('data');
    expect($versions[0]['version_number'])->toBe(2); // Newest first
    expect($versions[0]['is_latest_version'])->toBeTrue();
    expect($versions[1]['version_number'])->toBe(1);
    expect($versions[1]['is_latest_version'])->toBeFalse();

    // Step 4: Restore old version (version 1 -> creates version 3)
    $restoreResponse = $this->actingAs($this->itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1Id}/restore");

    $restoreResponse->assertCreated();
    $version3Id = $restoreResponse->json('data.id');
    $version3 = ImplementationFile::find($version3Id);

    expect($version3->version_number)->toBe(3);
    expect($version3->version_group_id)->toBe($version1->id);
    expect($version3->is_latest_version)->toBeTrue();
    expect($version3->uploaded_by)->toBe($this->itManager->id);

    // Verify version 2 is no longer the latest
    expect($version2->fresh()->is_latest_version)->toBeFalse();

    // Verify file content was copied
    Storage::disk('local')->assertExists($version3->file_path);
    $restoredContent = Storage::disk('local')->get($version3->file_path);
    $originalContent = Storage::disk('local')->get($version1->file_path);
    expect($restoredContent)->toBe($originalContent);

    // Step 5: Verify history now shows 3 versions
    $finalHistoryResponse = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1Id}/versions");

    $finalHistoryResponse->assertOk()
        ->assertJsonCount(3, 'data');

    $finalVersions = $finalHistoryResponse->json('data');
    expect($finalVersions[0]['version_number'])->toBe(3);
    expect($finalVersions[0]['is_latest_version'])->toBeTrue();
});

/**
 * End-to-end Test 2: Version comparison workflow
 * Create multiple versions and compare them via the versions endpoint
 */
test('version comparison workflow with multiple versions', function () {
    // Create 3 versions of a PDF file
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'rack_layout.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 rack layout v1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'rack_layout.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 rack layout v2');

    $version3 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'rack_layout.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 3,
        ]);
    Storage::disk('local')->put($version3->file_path, '%PDF-1.4 rack layout v3');

    // Fetch versions for comparison
    $response = $this->actingAs($this->operator)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version2->id}/versions");

    $response->assertOk()
        ->assertJsonCount(3, 'data');

    $versions = $response->json('data');

    // Verify each version has data needed for comparison view
    foreach ($versions as $version) {
        expect($version)->toHaveKeys([
            'id',
            'version_number',
            'preview_url',
            'download_url',
            'mime_type',
            'created_at',
        ]);
        expect($version['preview_url'])->not->toBeEmpty();
    }

    // Verify we can access preview URLs for comparison
    $previewUrl1 = $versions[2]['preview_url']; // Version 1
    $previewUrl3 = $versions[0]['preview_url']; // Version 3

    // Both preview URLs should be accessible
    $preview1Response = $this->actingAs($this->operator)->get($previewUrl1);
    $preview3Response = $this->actingAs($this->operator)->get($previewUrl3);

    $preview1Response->assertOk();
    $preview3Response->assertOk();
});

/**
 * Edge Case Test: Restore creates correct version_number when gaps exist
 */
test('restore creates correct version number when version gaps exist due to soft deletes', function () {
    // Create a version chain with versions 1, 2, 3
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'gap_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'gap_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2');

    $version3 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'gap_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 3,
        ]);
    Storage::disk('local')->put($version3->file_path, '%PDF-1.4 version 3');

    // Soft-delete version 2 (creates a gap)
    $version2->delete();

    // Verify we now only see versions 1 and 3 (with a gap at 2)
    $versionsBeforeRestore = ImplementationFile::where('version_group_id', $version1->id)->pluck('version_number')->toArray();
    expect($versionsBeforeRestore)->toContain(1, 3);
    expect($versionsBeforeRestore)->not->toContain(2);

    // Restore version 1 - should create version 4 (max + 1), NOT version 2 to fill the gap
    $restoreResponse = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/restore");

    $restoreResponse->assertCreated();

    $newVersionNumber = $restoreResponse->json('data.version_number');

    // The restore should use max(version_number) + 1, which is 4 (not 2)
    expect($newVersionNumber)->toBe(4);

    // Verify the restored version is now the latest
    expect($restoreResponse->json('data.is_latest_version'))->toBeTrue();
});

/**
 * Edge Case Test: First file upload correctly sets version_group_id to own id
 */
test('first file upload via API sets version_group_id to own id', function () {
    // Upload a brand new file
    $file = UploadedFile::fake()->createWithContent('new_implementation.pdf', '%PDF-1.4 new file content');
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file,
            'description' => 'Brand new file',
        ]);

    $response->assertCreated();

    $fileId = $response->json('data.id');
    $file = ImplementationFile::find($fileId);

    // Verify version_group_id is set to the file's own id
    expect($file->version_group_id)->toBe($file->id);
    expect($file->version_number)->toBe(1);
    expect($file->is_latest_version)->toBeTrue();
    expect($file->has_multiple_versions)->toBeFalse();
});

/**
 * Permission Test: Auditor can view versions but cannot restore
 */
test('auditor can view versions but cannot restore them', function () {
    // Create a file with multiple versions
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'auditor_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'auditor_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2');

    // Auditor CAN view versions (has datacenter access)
    $viewResponse = $this->actingAs($this->auditor)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/versions");

    $viewResponse->assertOk()
        ->assertJsonCount(2, 'data');

    // Auditor CANNOT restore versions (read-only role)
    $restoreResponse = $this->actingAs($this->auditor)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/restore");

    $restoreResponse->assertForbidden();
});

/**
 * Permission Test: Operator can view versions but cannot restore
 */
test('operator can view versions but cannot restore them', function () {
    // Create a file
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'operator_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $file->update(['version_group_id' => $file->id]);
    Storage::disk('local')->put($file->file_path, '%PDF-1.4 content');

    // Operator CAN view versions
    $viewResponse = $this->actingAs($this->operator)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions");

    $viewResponse->assertOk();

    // Operator CANNOT restore
    $restoreResponse = $this->actingAs($this->operator)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/restore");

    $restoreResponse->assertForbidden();
});

/**
 * Permission Test: All users with datacenter access can view version history
 */
test('all users with datacenter access can view version history', function () {
    // Create a file with versions
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'access_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $file->update(['version_group_id' => $file->id]);
    Storage::disk('local')->put($file->file_path, '%PDF-1.4 content');

    // Admin can view
    $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions")
        ->assertOk();

    // IT Manager can view
    $this->actingAs($this->itManager)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions")
        ->assertOk();

    // Operator (with datacenter access) can view
    $this->actingAs($this->operator)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions")
        ->assertOk();

    // Auditor (with datacenter access) can view
    $this->actingAs($this->auditor)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions")
        ->assertOk();

    // User without datacenter access cannot view
    $noAccessUser = User::factory()->create();
    $noAccessUser->assignRole('Operator');
    // Note: not attaching this user to the datacenter

    $this->actingAs($noAccessUser)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions")
        ->assertForbidden();
});

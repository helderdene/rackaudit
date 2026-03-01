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

    // Create Administrator user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create Operator user (read-only for implementation files)
    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create a datacenter for testing
    $this->datacenter = Datacenter::factory()->create();

    // Assign datacenter access to operator
    $this->operator->datacenters()->attach($this->datacenter);
});

/**
 * Test 1: Modal displays list of versions with correct data
 * The versions endpoint should return all versions with correct metadata.
 */
test('version history modal displays list of versions with correct data', function () {
    // Create a version chain with 3 versions
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
            'file_size' => 1024,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
            'file_size' => 2048,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2');

    $version3 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 3,
            'file_size' => 3072,
        ]);
    Storage::disk('local')->put($version3->file_path, '%PDF-1.4 version 3');

    // Fetch versions (this is the API the modal will use)
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/versions");

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'version_number',
                    'formatted_file_size',
                    'created_at',
                    'uploader' => ['id', 'name'],
                    'download_url',
                ],
            ],
        ]);

    // Verify data is correct for each version
    $data = $response->json('data');
    expect($data[0]['version_number'])->toBe(3);
    expect($data[1]['version_number'])->toBe(2);
    expect($data[2]['version_number'])->toBe(1);
});

/**
 * Test 2: Current/latest version is visually distinguishable
 * The is_latest_version flag should be true only for the latest version.
 */
test('current latest version is visually distinguished via is_latest_version flag', function () {
    // Create a version chain
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2');

    // Fetch versions
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/versions");

    $response->assertOk();

    $data = $response->json('data');

    // Version 2 (newest, first in list) should be the latest
    expect($data[0]['version_number'])->toBe(2);
    expect($data[0]['is_latest_version'])->toBeTrue();

    // Version 1 (older, second in list) should not be the latest
    expect($data[1]['version_number'])->toBe(1);
    expect($data[1]['is_latest_version'])->toBeFalse();
});

/**
 * Test 3: Download button triggers file download
 * The download_url should be correct and download should work.
 */
test('download button triggers file download via download_url', function () {
    // Create a file
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $file->update(['version_group_id' => $file->id]);

    $fileContent = '%PDF-1.4 test content for download';
    Storage::disk('local')->put($file->file_path, $fileContent);

    // Get versions to retrieve the download URL
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions");

    $response->assertOk();
    $downloadUrl = $response->json('data.0.download_url');

    // Verify download URL is valid and returns the file with correct headers
    $downloadResponse = $this->actingAs($this->admin)->get($downloadUrl);

    $downloadResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition');

    // For streamed responses, we verify the file exists in storage instead of checking content
    Storage::disk('local')->assertExists($file->file_path);
    expect(Storage::disk('local')->get($file->file_path))->toBe($fileContent);
});

/**
 * Test 4: Restore button availability based on permissions and version status
 * Restore should be possible for non-current versions by authorized users.
 */
test('restore button available for non-current versions by authorized users', function () {
    // Create a version chain
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2');

    // Admin can restore older version (version 1 -> creates version 3)
    $restoreResponse = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/restore");

    $restoreResponse->assertCreated()
        ->assertJsonPath('data.version_number', 3)
        ->assertJsonPath('data.is_latest_version', true);

    // Operator cannot restore (read-only access)
    $operatorRestoreResponse = $this->actingAs($this->operator)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/restore");

    $operatorRestoreResponse->assertForbidden();
});

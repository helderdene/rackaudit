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
 * Test 1: Version badge displays for files with multiple versions
 * Files with has_multiple_versions=true should include version_number for badge display.
 */
test('version badge data is available for files with multiple versions', function () {
    // Create a version chain with 3 versions
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

    $version3 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 3,
        ]);
    Storage::disk('local')->put($version3->file_path, '%PDF-1.4 version 3');

    // Create a single-version file for comparison
    $singleFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->xlsx()
        ->create([
            'original_name' => 'single_file.xlsx',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $singleFile->update(['version_group_id' => $singleFile->id]);
    Storage::disk('local')->put($singleFile->file_path, 'xlsx content');

    // Load the datacenter show page which includes implementation files
    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Datacenters/Show')
            // Check that version data is included in the response
            ->has('implementationFiles.0.version_number')
            ->has('implementationFiles.0.has_multiple_versions')
            ->has('implementationFiles.0.is_latest_version')
            ->has('implementationFiles.0.version_group_id')
        );

    // Get the implementation files from the response to verify specific values
    $implementationFiles = $response->original->getData()['page']['props']['implementationFiles'];

    // Find a file from the multi-version group (any version should have has_multiple_versions = true)
    $multiVersionFile = collect($implementationFiles)->firstWhere('original_name', 'implementation_spec.pdf');
    expect($multiVersionFile['has_multiple_versions'])->toBeTrue();

    // Find the single-version file (should have has_multiple_versions = false)
    $singleVersionFile = collect($implementationFiles)->firstWhere('original_name', 'single_file.xlsx');
    expect($singleVersionFile['has_multiple_versions'])->toBeFalse();
    expect($singleVersionFile['version_number'])->toBe(1);
    expect($singleVersionFile['is_latest_version'])->toBeTrue();
});

/**
 * Test 2: History button functionality - versions endpoint is accessible for all files
 * The versions endpoint should return data for any file, even single-version files.
 */
test('history button can open versions endpoint for all files', function () {
    // Create a single-version file
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'single_version.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $file->update(['version_group_id' => $file->id]);
    Storage::disk('local')->put($file->file_path, '%PDF-1.4 single version');

    // The versions endpoint should work for single-version files too
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.version_number', 1)
        ->assertJsonPath('data.0.is_latest_version', true)
        ->assertJsonPath('data.0.has_multiple_versions', false);

    // Operators should also be able to access versions (view permission)
    $operatorResponse = $this->actingAs($this->operator)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions");

    $operatorResponse->assertOk();
});

/**
 * Test 3: Compare button appears only for files with 2+ versions
 * Files with has_multiple_versions=true should show the compare option.
 */
test('compare functionality requires files with multiple versions', function () {
    // Create a single-version file
    $singleFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'single.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $singleFile->update(['version_group_id' => $singleFile->id]);
    Storage::disk('local')->put($singleFile->file_path, '%PDF-1.4 single');

    // Create a multi-version file
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'multi.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'multi.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2');

    // Load the datacenter show page
    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk();

    $implementationFiles = $response->original->getData()['page']['props']['implementationFiles'];

    // Single-version file should NOT have compare capability
    $singleVersionFile = collect($implementationFiles)->firstWhere('original_name', 'single.pdf');
    expect($singleVersionFile['has_multiple_versions'])->toBeFalse();

    // Multi-version file SHOULD have compare capability (check any version in the group)
    $multiVersionFiles = collect($implementationFiles)->where('original_name', 'multi.pdf');
    foreach ($multiVersionFiles as $file) {
        expect($file['has_multiple_versions'])->toBeTrue();
    }
});

/**
 * Test 4: Compare button opens comparison with version data available
 * When comparing versions, both versions should be accessible via the versions endpoint.
 */
test('compare dialog can access all versions for comparison', function () {
    // Create a version chain with 3 versions for comprehensive comparison
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'compare_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'compare_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2');

    $version3 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'compare_test.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 3,
        ]);
    Storage::disk('local')->put($version3->file_path, '%PDF-1.4 version 3');

    // Fetch versions for comparison - should return all 3 versions
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/versions");

    $response->assertOk()
        ->assertJsonCount(3, 'data');

    $versions = $response->json('data');

    // Verify all versions have required data for comparison dialog
    foreach ($versions as $version) {
        expect($version)->toHaveKeys([
            'id',
            'version_number',
            'created_at',
            'download_url',
            'preview_url',
            'formatted_file_size',
            'is_latest_version',
            'has_multiple_versions',
        ]);
    }

    // Verify correct ordering (newest first for comparison dropdowns)
    expect($versions[0]['version_number'])->toBe(3);
    expect($versions[1]['version_number'])->toBe(2);
    expect($versions[2]['version_number'])->toBe(1);

    // Verify only the latest version is marked as current
    expect($versions[0]['is_latest_version'])->toBeTrue();
    expect($versions[1]['is_latest_version'])->toBeFalse();
    expect($versions[2]['is_latest_version'])->toBeFalse();
});

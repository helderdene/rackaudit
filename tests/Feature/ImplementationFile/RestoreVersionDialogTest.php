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
 * Test 1: Restore endpoint returns correct confirmation-friendly response
 * The response should include the message explaining what happened
 */
test('restore endpoint returns confirmation message with new version details', function () {
    // Create a version chain with 2 versions
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1 content');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2 content');

    // Restore version 1 - this is what the dialog confirmation triggers
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/restore");

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'file_name',
                'original_name',
                'version_number',
                'version_group_id',
                'is_latest_version',
                'has_multiple_versions',
                'uploader' => ['id', 'name'],
                'created_at',
            ],
            'message',
        ])
        ->assertJsonPath('message', 'Version restored successfully.')
        ->assertJsonPath('data.version_number', 3)
        ->assertJsonPath('data.is_latest_version', true);
});

/**
 * Test 2: Restore action creates a new version and sets correct uploader
 * The restored version should have the current user as the uploader
 */
test('restore action creates new version with current user as uploader', function () {
    // Create an IT Manager to do the restore
    $itManager = User::factory()->create(['name' => 'IT Manager User']);
    $itManager->assignRole('IT Manager');

    // Create a file by admin
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $file->update(['version_group_id' => $file->id]);
    Storage::disk('local')->put($file->file_path, '%PDF-1.4 original content');

    // IT Manager restores the file
    $response = $this->actingAs($itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/restore");

    $response->assertCreated()
        ->assertJsonPath('data.uploader.id', $itManager->id)
        ->assertJsonPath('data.uploader.name', 'IT Manager User');

    // Verify in database the uploader is correct
    $newVersion = ImplementationFile::find($response->json('data.id'));
    expect($newVersion->uploaded_by)->toBe($itManager->id);
});

/**
 * Test 3: Restore returns success response suitable for dialog close
 * The response should be usable to trigger dialog close and emit events
 */
test('restore returns success response suitable for dialog close and refresh', function () {
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

    // Restore version 1
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/restore");

    // Response should be 201 Created (success) with the new version data
    $response->assertCreated();

    $newVersionData = $response->json('data');

    // The new version should have all the data needed to update the UI
    expect($newVersionData)->toHaveKeys([
        'id',
        'file_name',
        'original_name',
        'version_number',
        'version_group_id',
        'is_latest_version',
        'has_multiple_versions',
        'created_at',
        'download_url',
    ]);

    // After restore, fetching versions should show the new version as latest
    $versionsResponse = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/versions");

    $versionsResponse->assertOk()
        ->assertJsonCount(3, 'data');

    $versions = $versionsResponse->json('data');
    // Version 3 (restored) should be first and latest
    expect($versions[0]['version_number'])->toBe(3);
    expect($versions[0]['is_latest_version'])->toBeTrue();
    expect($versions[0]['id'])->toBe($newVersionData['id']);
});

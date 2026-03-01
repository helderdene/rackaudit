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

    // Create a datacenter for testing
    $this->datacenter = Datacenter::factory()->create();
});

/**
 * Test 1: Comparison displays two versions side-by-side
 * The versions endpoint returns data suitable for side-by-side comparison on desktop.
 */
test('comparison displays two versions with correct data for side-by-side view', function () {
    // Create a version chain with 3 versions
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1 content');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2 content');

    $version3 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 3,
        ]);
    Storage::disk('local')->put($version3->file_path, '%PDF-1.4 version 3 content');

    // Fetch versions for comparison
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/versions");

    $response->assertOk()
        ->assertJsonCount(3, 'data');

    $data = $response->json('data');

    // Verify each version has the data needed for comparison display
    foreach ($data as $version) {
        expect($version)->toHaveKeys([
            'id',
            'version_number',
            'created_at',
            'mime_type',
            'download_url',
            'preview_url',
        ]);
    }

    // Verify versions are ordered newest first (for default comparison)
    expect($data[0]['version_number'])->toBe(3);
    expect($data[1]['version_number'])->toBe(2);
    expect($data[2]['version_number'])->toBe(1);
});

/**
 * Test 2: Comparison stacks versions for mobile (API provides responsive data)
 * The API returns sufficient data for the frontend to render in stacked layout.
 */
test('comparison provides data suitable for stacked mobile layout', function () {
    // Create two versions
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'doc.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, '%PDF-1.4 version 1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'doc.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2');

    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/versions");

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $data = $response->json('data');

    // Each version should have version_number for labeling
    expect($data[0]['version_number'])->toBeInt();
    expect($data[1]['version_number'])->toBeInt();

    // Each version should have a preview_url for displaying content
    expect($data[0])->toHaveKey('preview_url');
    expect($data[1])->toHaveKey('preview_url');

    // Each version should have created_at for date display in dropdowns
    expect($data[0])->toHaveKey('created_at');
    expect($data[1])->toHaveKey('created_at');
});

/**
 * Test 3: Version selection dropdowns can use returned versions array
 * All versions in a group are returned for selection.
 */
test('version selection dropdowns can populate from versions array', function () {
    // Create a version chain with 4 versions
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create([
            'original_name' => 'image.png',
            'mime_type' => 'image/png',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);
    Storage::disk('local')->put($version1->file_path, 'fake png content v1');

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create([
            'original_name' => 'image.png',
            'mime_type' => 'image/png',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);
    Storage::disk('local')->put($version2->file_path, 'fake png content v2');

    $version3 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create([
            'original_name' => 'image.png',
            'mime_type' => 'image/png',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 3,
        ]);
    Storage::disk('local')->put($version3->file_path, 'fake png content v3');

    $version4 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create([
            'original_name' => 'image.png',
            'mime_type' => 'image/png',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 4,
        ]);
    Storage::disk('local')->put($version4->file_path, 'fake png content v4');

    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/versions");

    $response->assertOk()
        ->assertJsonCount(4, 'data');

    $data = $response->json('data');

    // All 4 versions should be available for dropdown selection
    $versionNumbers = array_column($data, 'version_number');
    expect($versionNumbers)->toContain(1, 2, 3, 4);

    // Each version should have date for display in dropdown
    foreach ($data as $version) {
        expect($version)->toHaveKey('created_at');
        expect($version['created_at'])->not->toBeEmpty();
    }
});

/**
 * Test 4: PDF and image files have appropriate URLs for viewers
 * Both PDF and image file types return preview URLs for rendering.
 */
test('pdf and image files have preview urls for viewers', function () {
    // Create a PDF file
    $pdfFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'document.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $pdfFile->update(['version_group_id' => $pdfFile->id]);
    Storage::disk('local')->put($pdfFile->file_path, '%PDF-1.4 pdf content');

    // Create an image file
    $imageFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create([
            'original_name' => 'diagram.png',
            'mime_type' => 'image/png',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $imageFile->update(['version_group_id' => $imageFile->id]);
    Storage::disk('local')->put($imageFile->file_path, 'fake png content');

    // Check PDF file has preview URL
    $pdfResponse = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$pdfFile->id}/versions");

    $pdfResponse->assertOk();
    $pdfData = $pdfResponse->json('data.0');
    expect($pdfData['mime_type'])->toBe('application/pdf');
    expect($pdfData['preview_url'])->not->toBeEmpty();

    // Check image file has preview URL
    $imageResponse = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$imageFile->id}/versions");

    $imageResponse->assertOk();
    $imageData = $imageResponse->json('data.0');
    expect($imageData['mime_type'])->toBe('image/png');
    expect($imageData['preview_url'])->not->toBeEmpty();
});

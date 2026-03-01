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

    // Create IT Manager user
    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    // Create Operator user (read-only for implementation files)
    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create Auditor user (read-only for implementation files)
    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('Auditor');

    // Create a datacenter for testing
    $this->datacenter = Datacenter::factory()->create();

    // Assign datacenter access to non-admin users
    $this->operator->datacenters()->attach($this->datacenter);
    $this->auditor->datacenters()->attach($this->datacenter);
});

/**
 * Test 1: GET /versions returns all versions ordered by version_number desc
 */
test('versions endpoint returns all versions ordered by version_number desc', function () {
    // Create a version chain with 3 versions
    $version1 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $version1->update(['version_group_id' => $version1->id]);

    $version2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 2,
        ]);

    $version3 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_group_id' => $version1->id,
            'version_number' => 3,
        ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$version1->id}/versions");

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'file_name',
                    'original_name',
                    'version_number',
                    'version_group_id',
                    'is_latest_version',
                    'has_multiple_versions',
                    'created_at',
                ],
            ],
        ]);

    // Verify ordering: version 3 first (newest), version 1 last (oldest)
    $data = $response->json('data');
    expect($data[0]['version_number'])->toBe(3);
    expect($data[1]['version_number'])->toBe(2);
    expect($data[2]['version_number'])->toBe(1);
});

/**
 * Test 2: GET /versions returns 403 for users without datacenter access
 */
test('versions endpoint returns 403 for users without datacenter access', function () {
    // Create a user without datacenter access
    $userWithoutAccess = User::factory()->create();
    $userWithoutAccess->assignRole('Operator');

    // Create a version chain
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create([
            'original_name' => 'implementation_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $file->update(['version_group_id' => $file->id]);

    $response = $this->actingAs($userWithoutAccess)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions");

    $response->assertForbidden();

    // User with datacenter access can view versions
    $response = $this->actingAs($this->operator)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/versions");

    $response->assertOk();
});

/**
 * Test 3: POST /restore creates new version from old file content
 */
test('restore endpoint creates new version from old file content', function () {
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

    // Create file content for version 1
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

    // Create file content for version 2
    Storage::disk('local')->put($version2->file_path, '%PDF-1.4 version 2 content');

    // Restore version 1 (should create version 3)
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
                'uploader',
            ],
            'message',
        ]);

    // Verify new version was created with incremented version number
    $data = $response->json('data');
    expect($data['version_number'])->toBe(3);
    expect($data['version_group_id'])->toBe($version1->id);
    expect($data['is_latest_version'])->toBeTrue();
    expect($data['original_name'])->toBe('implementation_spec.pdf');

    // Verify database has 3 versions now
    $this->assertDatabaseCount('implementation_files', 3);
});

/**
 * Test 4: POST /restore returns 403 for non-admin/IT Manager users
 */
test('restore endpoint returns 403 for non-admin users', function () {
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

    // Create file content
    Storage::disk('local')->put($file->file_path, '%PDF-1.4 content');

    // Operator cannot restore
    $response = $this->actingAs($this->operator)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/restore");
    $response->assertForbidden();

    // Auditor cannot restore
    $response = $this->actingAs($this->auditor)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/restore");
    $response->assertForbidden();

    // Admin can restore
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/restore");
    $response->assertCreated();

    // Create another file for IT Manager test
    $file2 = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'another_spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);
    $file2->update(['version_group_id' => $file2->id]);
    Storage::disk('local')->put($file2->file_path, '%PDF-1.4 content');

    // IT Manager can restore
    $response = $this->actingAs($this->itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file2->id}/restore");
    $response->assertCreated();
});

/**
 * Test 5: POST /restore copies file in storage with new UUID filename
 */
test('restore endpoint copies file in storage with new UUID filename', function () {
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

    $originalContent = '%PDF-1.4 original content to restore';
    Storage::disk('local')->put($file->file_path, $originalContent);

    // Restore the file
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/restore");

    $response->assertCreated();

    // Get the new file from the response
    $newFileId = $response->json('data.id');
    $newFile = ImplementationFile::find($newFileId);

    // Verify original file still exists
    Storage::disk('local')->assertExists($file->file_path);

    // Verify new file exists with different path
    Storage::disk('local')->assertExists($newFile->file_path);
    expect($newFile->file_path)->not->toBe($file->file_path);

    // Verify new file has same content as original
    $newContent = Storage::disk('local')->get($newFile->file_path);
    expect($newContent)->toBe($originalContent);

    // Verify new file has UUID-based filename
    expect($newFile->file_name)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$/');
});

/**
 * Test 6: Store endpoint creates version chain for same original_name
 */
test('store endpoint creates version chain for same original_name', function () {
    $pdfContent = '%PDF-1.4 fake pdf content';

    // Upload first file
    $file1 = \Illuminate\Http\UploadedFile::fake()->createWithContent('implementation_spec.pdf', $pdfContent);
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file1,
            'description' => 'First version',
        ]);

    $response->assertCreated();
    $version1Data = $response->json('data');
    expect($version1Data['version_number'])->toBe(1);

    // Get the version_group_id from database (since resource might not return it yet)
    $version1 = ImplementationFile::find($version1Data['id']);
    expect($version1->version_group_id)->toBe($version1->id);

    // Upload second file with same name
    $file2 = \Illuminate\Http\UploadedFile::fake()->createWithContent('implementation_spec.pdf', $pdfContent . ' v2');
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file2,
            'description' => 'Second version',
        ]);

    $response->assertCreated();
    $version2Data = $response->json('data');
    expect($version2Data['version_number'])->toBe(2);

    // Verify both files share same version_group_id
    $version2 = ImplementationFile::find($version2Data['id']);
    expect($version2->version_group_id)->toBe($version1->id);

    // Verify both files exist in database (not replaced)
    $this->assertDatabaseCount('implementation_files', 2);
});

/**
 * Test 7: Store endpoint does NOT delete old file from storage
 */
test('store endpoint does not delete old file from storage', function () {
    $pdfContent = '%PDF-1.4 version 1 content';

    // Upload first file
    $file1 = \Illuminate\Http\UploadedFile::fake()->createWithContent('implementation_spec.pdf', $pdfContent);
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file1,
        ]);

    $response->assertCreated();
    $version1 = ImplementationFile::find($response->json('data.id'));

    // Verify first file exists in storage
    Storage::disk('local')->assertExists($version1->file_path);

    // Upload second file with same name
    $file2 = \Illuminate\Http\UploadedFile::fake()->createWithContent('implementation_spec.pdf', '%PDF-1.4 version 2 content');
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file2,
        ]);

    $response->assertCreated();
    $version2 = ImplementationFile::find($response->json('data.id'));

    // Verify BOTH files exist in storage (old file was NOT deleted)
    Storage::disk('local')->assertExists($version1->file_path);
    Storage::disk('local')->assertExists($version2->file_path);

    // Verify files have different paths
    expect($version1->file_path)->not->toBe($version2->file_path);
});

<?php

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
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('Auditor');

    // Create a datacenter
    $this->datacenter = Datacenter::factory()->create();

    // Assign datacenter access to non-admin users
    $this->operator->datacenters()->attach($this->datacenter);
    $this->auditor->datacenters()->attach($this->datacenter);
});

/**
 * Integration Test 1: Soft-deleted files are not shown in the file list
 */
test('soft_deleted files are not shown in list', function () {
    // Create active and soft-deleted files
    $activeFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id, 'original_name' => 'active.pdf']);

    $deletedFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id, 'original_name' => 'deleted.pdf']);

    // Soft delete one file
    $deletedFile->delete();

    // Verify via API that only active file is returned
    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['original_name' => 'active.pdf'])
        ->assertJsonMissing(['original_name' => 'deleted.pdf']);

    // Verify via Inertia page
    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('implementationFiles', 1)
        );
});

/**
 * Integration Test 2: File upload rejects invalid file types
 */
test('file upload rejects invalid file types', function () {
    $invalidFile = UploadedFile::fake()->create('malware.exe', 1024, 'application/x-msdownload');

    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $invalidFile,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);

    // Verify no file was created
    $this->assertDatabaseCount('implementation_files', 0);
});

/**
 * Integration Test 3: IT Manager can delete files
 */
test('it_manager can delete files', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id]);

    Storage::disk('local')->put($file->file_path, 'test content');

    $response = $this->actingAs($this->itManager)
        ->deleteJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}");

    $response->assertNoContent();

    // Verify soft delete
    expect(ImplementationFile::find($file->id))->toBeNull();
    expect(ImplementationFile::withTrashed()->find($file->id))->not->toBeNull();
});

/**
 * Integration Test 4: Auditor can view file list and download files
 */
test('auditor can view file list and download files', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create(['uploaded_by' => $this->admin->id]);

    Storage::disk('local')->put($file->file_path, '%PDF-1.4 test content');

    // Auditor can view file list via API
    $response = $this->actingAs($this->auditor)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files");

    $response->assertOk()
        ->assertJsonCount(1, 'data');

    // Auditor can view datacenter show page with files
    $response = $this->actingAs($this->auditor)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('implementationFiles', 1)
        );

    // Auditor can download file
    $response = $this->actingAs($this->auditor)
        ->get("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/download");

    $response->assertOk();
});

/**
 * Integration Test 5: End-to-end - Admin uploads file and sees it in datacenter list
 */
test('admin uploads file and sees it in datacenter show page', function () {
    $pdfContent = '%PDF-1.4 implementation specification content';
    $file = UploadedFile::fake()->createWithContent('implementation_spec.pdf', $pdfContent);

    // Upload the file
    $uploadResponse = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file,
            'description' => 'Network implementation specification',
        ]);

    $uploadResponse->assertCreated();
    $uploadedFileData = $uploadResponse->json('data');

    // View datacenter show page and verify file is listed
    $showResponse = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $showResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Datacenters/Show')
            ->has('implementationFiles', 1)
            ->has('implementationFiles.0', fn ($file) => $file
                ->where('original_name', 'implementation_spec.pdf')
                ->where('description', 'Network implementation specification')
                ->has('download_url')
                ->has('preview_url')
                ->etc()
            )
        );
});

/**
 * Integration Test 6: Download returns 404 for missing storage file
 */
test('download returns 404 for missing storage file', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create(['uploaded_by' => $this->admin->id]);

    // Do NOT create the file in storage - simulate missing file

    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/download");

    $response->assertNotFound()
        ->assertJsonFragment(['message' => 'File not found.']);
});

/**
 * Integration Test 7: Preview returns 404 for missing storage file
 */
test('preview returns 404 for missing pdf storage file', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create(['uploaded_by' => $this->admin->id]);

    // Do NOT create the file in storage - simulate missing file

    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/preview");

    $response->assertNotFound()
        ->assertJsonFragment(['message' => 'File not found.']);
});

/**
 * Integration Test 8: New versions are linked via version_group_id
 */
test('new versions are linked via version_group_id', function () {
    // Create initial file
    $initialFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);

    // Set version_group_id to its own ID (establishing it as version chain root)
    $initialFile->update(['version_group_id' => $initialFile->id]);

    Storage::disk('local')->put($initialFile->file_path, 'old content');

    // Upload new version as IT Manager
    $newPdfContent = '%PDF-1.4 new content';
    $newFile = UploadedFile::fake()->createWithContent('spec.pdf', $newPdfContent);

    $this->actingAs($this->itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $newFile,
        ]);

    // Check that original file still exists with version 1
    $initialFile->refresh();
    expect($initialFile->version_number)->toBe(1);
    expect($initialFile->version_group_id)->toBe($initialFile->id);

    // Check that new file is version 2 in same group
    $newFileRecord = ImplementationFile::where('id', '!=', $initialFile->id)
        ->where('datacenter_id', $this->datacenter->id)
        ->first();

    expect($newFileRecord->version_number)->toBe(2);
    expect($newFileRecord->version_group_id)->toBe($initialFile->id);
    expect($newFileRecord->uploaded_by)->toBe($this->itManager->id);
});

/**
 * Integration Test 9: Show action returns file details
 */
test('show action returns file details with download url', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'uploaded_by' => $this->admin->id,
            'description' => 'Test description',
        ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'file_name',
                'original_name',
                'description',
                'mime_type',
                'formatted_file_size',
                'file_type_label',
                'uploader',
                'download_url',
                'preview_url',
                'created_at',
            ],
        ])
        ->assertJsonFragment([
            'id' => $file->id,
            'description' => 'Test description',
        ]);
});

/**
 * Integration Test 10: Operator cannot upload files (authorization)
 */
test('operator cannot upload implementation files', function () {
    $pdfContent = '%PDF-1.4 fake content';
    $file = UploadedFile::fake()->createWithContent('test.pdf', $pdfContent);

    $response = $this->actingAs($this->operator)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file,
        ]);

    $response->assertForbidden();

    // Verify no file was created
    $this->assertDatabaseCount('implementation_files', 0);
});

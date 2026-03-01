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
 * Test 1: Index returns paginated files for a datacenter
 */
test('index returns paginated files for a datacenter', function () {
    // Create files for the datacenter
    $files = ImplementationFile::factory()
        ->count(3)
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files");

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'file_name',
                    'original_name',
                    'description',
                    'mime_type',
                    'formatted_file_size',
                    'file_type_label',
                    'uploader',
                    'created_at',
                ],
            ],
        ]);
});

/**
 * Test 2: Store creates file record and stores file on disk
 */
test('store creates file record and stores file on disk', function () {
    $pdfContent = '%PDF-1.4 fake pdf content';
    $file = UploadedFile::fake()->createWithContent('implementation_spec.pdf', $pdfContent);

    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file,
            'description' => 'Test implementation specification',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'file_name',
                'original_name',
                'description',
                'mime_type',
                'formatted_file_size',
                'file_type_label',
                'download_url',
            ],
            'message',
        ])
        ->assertJsonFragment([
            'original_name' => 'implementation_spec.pdf',
            'description' => 'Test implementation specification',
        ]);

    // Verify database record was created
    $this->assertDatabaseHas('implementation_files', [
        'datacenter_id' => $this->datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'description' => 'Test implementation specification',
        'uploaded_by' => $this->admin->id,
    ]);

    // Verify file was stored on disk
    $implementationFile = ImplementationFile::first();
    Storage::disk('local')->assertExists($implementationFile->file_path);
});

/**
 * Test 3: Store creates new version when uploading file with same original_name
 */
test('store creates new version when uploading file with same original_name', function () {
    // Create an existing file first
    $existingFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'original_name' => 'spec.pdf',
            'uploaded_by' => $this->admin->id,
            'version_number' => 1,
        ]);

    // Set version_group_id to its own ID (establishing it as version chain root)
    $existingFile->update(['version_group_id' => $existingFile->id]);

    // Create a fake file in storage
    Storage::disk('local')->put($existingFile->file_path, 'old content');

    $pdfContent = '%PDF-1.4 new pdf content';
    $newFile = UploadedFile::fake()->createWithContent('spec.pdf', $pdfContent);

    $response = $this->actingAs($this->itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $newFile,
            'description' => 'Updated specification',
        ]);

    $response->assertCreated();

    // Verify old file record still exists (versioning preserves all versions)
    $existingFile->refresh();
    expect($existingFile->version_number)->toBe(1);

    // Verify old file still exists in storage (versioning preserves files)
    Storage::disk('local')->assertExists($existingFile->file_path);

    // Verify new file was created as version 2
    $this->assertDatabaseCount('implementation_files', 2);

    $newFileRecord = ImplementationFile::where('id', '!=', $existingFile->id)->first();
    expect($newFileRecord->version_group_id)->toBe($existingFile->id);
    expect($newFileRecord->version_number)->toBe(2);
    expect($newFileRecord->original_name)->toBe('spec.pdf');
});

/**
 * Test 4: Download streams file with authentication
 */
test('download streams file with authentication', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create(['uploaded_by' => $this->admin->id]);

    // Create the file in storage
    Storage::disk('local')->put($file->file_path, '%PDF-1.4 test content');

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/download");

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain($file->original_name);

    // Verify unauthenticated user cannot download
    $this->post('/logout');
    $response = $this->get("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/download");
    $response->assertRedirect(); // Redirects to login
});

/**
 * Test 5: Preview serves PDF inline
 */
test('preview serves PDF inline', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create(['uploaded_by' => $this->admin->id]);

    // Create the file in storage
    Storage::disk('local')->put($file->file_path, '%PDF-1.4 test content');

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/preview");

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('inline');

    // Test non-PDF file returns 415
    $xlsxFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->xlsx()
        ->create(['uploaded_by' => $this->admin->id]);

    Storage::disk('local')->put($xlsxFile->file_path, 'xlsx content');

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}/implementation-files/{$xlsxFile->id}/preview");

    $response->assertStatus(415);
});

/**
 * Test 6: Destroy soft-deletes record and removes file
 */
test('destroy soft-deletes record and removes file', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id]);

    // Create the file in storage
    Storage::disk('local')->put($file->file_path, 'test content');

    $response = $this->actingAs($this->admin)
        ->deleteJson("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}");

    $response->assertNoContent();

    // Verify soft delete
    expect(ImplementationFile::find($file->id))->toBeNull();
    expect(ImplementationFile::withTrashed()->find($file->id))->not->toBeNull();
    expect(ImplementationFile::withTrashed()->find($file->id)->deleted_at)->not->toBeNull();

    // Verify file was removed from storage
    Storage::disk('local')->assertMissing($file->file_path);
});

/**
 * Test 7: Authorization - Admin/IT Manager can upload/delete, Operator/Auditor read-only
 */
test('authorization allows Admin and IT Manager to upload and delete', function () {
    $pdfContent = '%PDF-1.4 test content';
    $file = UploadedFile::fake()->createWithContent('test.pdf', $pdfContent);

    // Admin can upload
    $response = $this->actingAs($this->admin)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file,
        ]);
    $response->assertCreated();

    // IT Manager can upload
    $file2 = UploadedFile::fake()->createWithContent('test2.pdf', $pdfContent);
    $response = $this->actingAs($this->itManager)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file2,
        ]);
    $response->assertCreated();

    // Operator cannot upload
    $file3 = UploadedFile::fake()->createWithContent('test3.pdf', $pdfContent);
    $response = $this->actingAs($this->operator)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file3,
        ]);
    $response->assertForbidden();

    // Auditor cannot upload
    $file4 = UploadedFile::fake()->createWithContent('test4.pdf', $pdfContent);
    $response = $this->actingAs($this->auditor)
        ->postJson("/datacenters/{$this->datacenter->id}/implementation-files", [
            'file' => $file4,
        ]);
    $response->assertForbidden();

    // Create a file for delete testing
    $existingFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id]);
    Storage::disk('local')->put($existingFile->file_path, 'content');

    // Operator cannot delete
    $response = $this->actingAs($this->operator)
        ->deleteJson("/datacenters/{$this->datacenter->id}/implementation-files/{$existingFile->id}");
    $response->assertForbidden();

    // Auditor cannot delete
    $response = $this->actingAs($this->auditor)
        ->deleteJson("/datacenters/{$this->datacenter->id}/implementation-files/{$existingFile->id}");
    $response->assertForbidden();

    // Admin can delete
    $response = $this->actingAs($this->admin)
        ->deleteJson("/datacenters/{$this->datacenter->id}/implementation-files/{$existingFile->id}");
    $response->assertNoContent();
});

/**
 * Test 8: Users must have datacenter access to access files
 */
test('users must have datacenter access to access files', function () {
    // Create a user without datacenter access
    $userWithoutAccess = User::factory()->create();
    $userWithoutAccess->assignRole('Operator');

    // Create a file
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id]);
    Storage::disk('local')->put($file->file_path, 'test content');

    // User without access cannot view index
    $response = $this->actingAs($userWithoutAccess)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files");
    $response->assertForbidden();

    // User without access cannot download
    $response = $this->actingAs($userWithoutAccess)
        ->get("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/download");
    $response->assertForbidden();

    // User with access (operator) can view index
    $response = $this->actingAs($this->operator)
        ->getJson("/datacenters/{$this->datacenter->id}/implementation-files");
    $response->assertOk();

    // User with access (auditor) can download
    $response = $this->actingAs($this->auditor)
        ->get("/datacenters/{$this->datacenter->id}/implementation-files/{$file->id}/download");
    $response->assertOk();
});

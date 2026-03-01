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
 * Test 1: Datacenter Show page returns implementation files data
 */
test('datacenter show page returns implementation files data', function () {
    // Create implementation files for the datacenter
    $files = ImplementationFile::factory()
        ->count(2)
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Datacenters/Show')
            ->has('implementationFiles', 2)
            ->has('canUploadFiles')
            ->has('canDeleteFiles')
        );
});

/**
 * Test 2: Datacenter Show page includes canUpload and canDelete permissions
 */
test('datacenter show page includes correct permissions for admin', function () {
    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canUploadFiles', true)
            ->where('canDeleteFiles', true)
        );
});

/**
 * Test 3: Datacenter Show page includes correct permissions for operator (read-only)
 */
test('datacenter show page includes correct permissions for operator', function () {
    $response = $this->actingAs($this->operator)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canUploadFiles', false)
            ->where('canDeleteFiles', false)
        );
});

/**
 * Test 4: Implementation files are returned with correct structure
 */
test('implementation files are returned with correct structure', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create([
            'uploaded_by' => $this->admin->id,
            'description' => 'Test description',
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('implementationFiles.0', fn ($file) => $file
                ->has('id')
                ->has('file_name')
                ->has('original_name')
                ->has('description')
                ->has('mime_type')
                ->has('formatted_file_size')
                ->has('file_type_label')
                ->has('uploader')
                ->has('version_number')
                ->has('version_group_id')
                ->has('has_multiple_versions')
                ->has('is_latest_version')
                ->has('created_at')
                ->has('download_url')
                ->has('preview_url')
                ->etc()
            )
        );
});

/**
 * Test 5: Empty state - No files returns empty array
 */
test('datacenter show page returns empty array when no files', function () {
    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('implementationFiles', 0)
        );
});

/**
 * Test 6: PDF files include preview_url in response
 */
test('pdf files include preview_url in response', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->pdf()
        ->create(['uploaded_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('implementationFiles.0.preview_url')
        );
});

/**
 * Test 7: Non-PDF files do not include preview_url
 */
test('non_pdf files do not include preview_url', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->xlsx()
        ->create(['uploaded_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('implementationFiles.0.preview_url')
        );
});

/**
 * Test 8: Implementation files include uploader information
 */
test('implementation files include uploader information', function () {
    $file = ImplementationFile::factory()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('implementationFiles.0.uploader', fn ($uploader) => $uploader
                ->has('id')
                ->has('name')
            )
        );
});

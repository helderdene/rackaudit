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

    $this->operator = User::factory()->create(['name' => 'Operator User']);
    $this->operator->assignRole('Operator');

    // Create datacenter
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);

    // Give operator access to datacenter
    $this->operator->datacenters()->attach($this->datacenter);
});

/**
 * Test 1: hasApprovedImplementationFiles returns false when datacenter has no approved files
 */
test('hasApprovedImplementationFiles returns false when datacenter has no approved files', function () {
    // Create only pending files
    $pendingFile = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($pendingFile->file_path, 'test content');

    expect($this->datacenter->hasApprovedImplementationFiles())->toBeFalse();
});

/**
 * Test 2: hasApprovedImplementationFiles returns true when datacenter has approved files
 */
test('hasApprovedImplementationFiles returns true when datacenter has approved files', function () {
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

    expect($this->datacenter->hasApprovedImplementationFiles())->toBeTrue();
});

/**
 * Test 3: hasApprovedImplementationFiles returns false when datacenter has no files at all
 */
test('hasApprovedImplementationFiles returns false when datacenter has no files', function () {
    // No files created for this datacenter
    expect($this->datacenter->hasApprovedImplementationFiles())->toBeFalse();
});

/**
 * Test 4: Datacenter show page includes hasApprovedImplementationFiles flag
 */
test('datacenter show page includes hasApprovedImplementationFiles flag', function () {
    // Create an approved file
    $approvedFile = ImplementationFile::factory()
        ->approved($this->admin)
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($approvedFile->file_path, 'test content');

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk();
    $response->assertInertia(fn ($assert) =>
        $assert->component('Datacenters/Show')
            ->has('datacenter.has_approved_implementation_files')
            ->where('datacenter.has_approved_implementation_files', true)
    );
});

/**
 * Test 5: Datacenter show page shows false when no approved files exist
 */
test('datacenter show page shows hasApprovedImplementationFiles false when no approved files', function () {
    // Create only a pending file
    $pendingFile = ImplementationFile::factory()
        ->pendingApproval()
        ->for($this->datacenter)
        ->create(['uploaded_by' => $this->operator->id]);
    Storage::disk('local')->put($pendingFile->file_path, 'test content');

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}");

    $response->assertOk();
    $response->assertInertia(fn ($assert) =>
        $assert->component('Datacenters/Show')
            ->has('datacenter.has_approved_implementation_files')
            ->where('datacenter.has_approved_implementation_files', false)
    );
});

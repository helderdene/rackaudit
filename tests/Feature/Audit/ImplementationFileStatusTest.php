<?php

use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->datacenter = Datacenter::factory()->create([
        'name' => 'Test Datacenter',
    ]);
});

/**
 * Test 1: Approved implementation file displays name and version
 */
test('approved implementation file displays name and version via API', function () {
    // Create an approved implementation file
    $implementationFile = ImplementationFile::factory()
        ->for($this->datacenter)
        ->approved()
        ->create([
            'original_name' => 'network_implementation_v2.xlsx',
            'version_number' => 2,
        ]);

    // Call the API endpoint
    $response = $this->actingAs($this->itManager)
        ->getJson("/api/audits/datacenters/{$this->datacenter->id}/implementation-file-status");

    $response->assertOk()
        ->assertJson([
            'has_approved_file' => true,
            'implementation_file' => [
                'id' => $implementationFile->id,
                'original_name' => 'network_implementation_v2.xlsx',
                'version_number' => 2,
            ],
        ]);
});

/**
 * Test 2: Error message shows when no approved implementation file exists
 */
test('error message shows when no approved implementation file exists', function () {
    // Create a pending (not approved) implementation file
    ImplementationFile::factory()
        ->for($this->datacenter)
        ->pendingApproval()
        ->create();

    // Call the API endpoint
    $response = $this->actingAs($this->itManager)
        ->getJson("/api/audits/datacenters/{$this->datacenter->id}/implementation-file-status");

    $response->assertOk()
        ->assertJson([
            'has_approved_file' => false,
            'implementation_file' => null,
            'error_message' => 'No approved implementation file exists for this datacenter. Please upload and approve an implementation file before creating a connection audit.',
        ]);

    // Test with no implementation files at all
    $emptyDatacenter = Datacenter::factory()->create();

    $response = $this->actingAs($this->itManager)
        ->getJson("/api/audits/datacenters/{$emptyDatacenter->id}/implementation-file-status");

    $response->assertOk()
        ->assertJson([
            'has_approved_file' => false,
            'implementation_file' => null,
        ]);
});

/**
 * Test 3: Link to Implementation Files page is included in response
 */
test('implementation files page link is included in response', function () {
    // Call the API endpoint for a datacenter without approved files
    $response = $this->actingAs($this->itManager)
        ->getJson("/api/audits/datacenters/{$this->datacenter->id}/implementation-file-status");

    $response->assertOk()
        ->assertJsonStructure([
            'has_approved_file',
            'implementation_file',
            'implementation_files_url',
        ]);

    // Verify the URL points to the implementation files page for this datacenter
    $responseData = $response->json();
    expect($responseData['implementation_files_url'])->toContain("/datacenters/{$this->datacenter->id}/implementation-files");
});

<?php

use App\Enums\EvidenceType;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Finding;
use App\Models\FindingEvidence;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

// Test 1: File upload creates FindingEvidence record with correct file_path
test('file upload creates FindingEvidence record with correct file_path', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $finding = Finding::factory()->create([
        'status' => FindingStatus::Open,
    ]);

    $file = UploadedFile::fake()->image('evidence-photo.jpg', 800, 600);

    $response = $this->actingAs($admin)->post("/findings/{$finding->id}/evidence", [
        'type' => 'file',
        'file' => $file,
    ]);

    $response->assertRedirect();

    // Verify the evidence record was created
    $evidence = FindingEvidence::where('finding_id', $finding->id)->first();

    expect($evidence)->not->toBeNull();
    expect($evidence->type)->toBe(EvidenceType::File);
    expect($evidence->original_filename)->toBe('evidence-photo.jpg');
    expect($evidence->mime_type)->toBe('image/jpeg');
    expect($evidence->file_path)->toStartWith("finding-evidence/{$finding->id}/");

    // Verify the file was stored
    Storage::disk('local')->assertExists($evidence->file_path);
});

// Test 2: Text note creation creates FindingEvidence record with content
test('text note creation creates FindingEvidence record with content', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $finding = Finding::factory()->create([
        'status' => FindingStatus::Open,
    ]);

    $textContent = 'This is a detailed observation about the finding. The cable was found to be incorrectly labeled and connected to the wrong port.';

    $response = $this->actingAs($admin)->post("/findings/{$finding->id}/evidence", [
        'type' => 'text',
        'content' => $textContent,
    ]);

    $response->assertRedirect();

    // Verify the evidence record was created
    $evidence = FindingEvidence::where('finding_id', $finding->id)->first();

    expect($evidence)->not->toBeNull();
    expect($evidence->type)->toBe(EvidenceType::Text);
    expect($evidence->content)->toBe($textContent);
    expect($evidence->file_path)->toBeNull();
    expect($evidence->original_filename)->toBeNull();
    expect($evidence->mime_type)->toBeNull();
});

// Test 3: Evidence deletion removes record and file from storage
test('evidence deletion removes record and file from storage', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $finding = Finding::factory()->create([
        'status' => FindingStatus::Open,
    ]);

    // Create a file evidence entry
    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');
    $filePath = "finding-evidence/{$finding->id}/test-file.pdf";
    Storage::disk('local')->put($filePath, $file->getContent());

    $evidence = FindingEvidence::create([
        'finding_id' => $finding->id,
        'type' => EvidenceType::File,
        'file_path' => $filePath,
        'original_filename' => 'document.pdf',
        'mime_type' => 'application/pdf',
    ]);

    // Verify file exists before deletion
    Storage::disk('local')->assertExists($filePath);

    $response = $this->actingAs($admin)->delete("/findings/{$finding->id}/evidence/{$evidence->id}");

    $response->assertRedirect();

    // Verify the evidence record was deleted
    expect(FindingEvidence::find($evidence->id))->toBeNull();

    // Verify the file was deleted from storage
    Storage::disk('local')->assertMissing($filePath);
});

// Test 4: File validation rejects invalid file types and oversized files
test('file validation rejects invalid file types and oversized files', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $finding = Finding::factory()->create([
        'status' => FindingStatus::Open,
    ]);

    // Test invalid file type (executable)
    $invalidFile = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

    $response = $this->actingAs($admin)->post("/findings/{$finding->id}/evidence", [
        'type' => 'file',
        'file' => $invalidFile,
    ]);

    $response->assertSessionHasErrors('file');

    // Test oversized file (over 10MB)
    $oversizedFile = UploadedFile::fake()->create('large-file.pdf', 11000, 'application/pdf'); // 11MB

    $response = $this->actingAs($admin)->post("/findings/{$finding->id}/evidence", [
        'type' => 'file',
        'file' => $oversizedFile,
    ]);

    $response->assertSessionHasErrors('file');

    // Verify no evidence was created
    expect(FindingEvidence::where('finding_id', $finding->id)->count())->toBe(0);
});

// Additional authorization test for evidence management
test('only authorized users can add evidence to a finding', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    $assignedOperator = User::factory()->create();
    $assignedOperator->assignRole('Operator');

    $unassignedOperator = User::factory()->create();
    $unassignedOperator->assignRole('Operator');

    // Create an audit and assign the operator to it
    $audit = Audit::factory()->create();
    $audit->assignees()->attach($assignedOperator->id);

    $finding = Finding::factory()->create([
        'audit_id' => $audit->id,
        'status' => FindingStatus::Open,
        'assigned_to' => $assignedOperator->id,
    ]);

    $textContent = 'Test evidence note';

    // Admin can add evidence
    $response = $this->actingAs($admin)->post("/findings/{$finding->id}/evidence", [
        'type' => 'text',
        'content' => $textContent,
    ]);
    $response->assertRedirect();

    // IT Manager can add evidence
    $response = $this->actingAs($itManager)->post("/findings/{$finding->id}/evidence", [
        'type' => 'text',
        'content' => $textContent,
    ]);
    $response->assertRedirect();

    // Assigned operator can add evidence
    $response = $this->actingAs($assignedOperator)->post("/findings/{$finding->id}/evidence", [
        'type' => 'text',
        'content' => $textContent,
    ]);
    $response->assertRedirect();

    // Unassigned operator cannot add evidence
    $response = $this->actingAs($unassignedOperator)->post("/findings/{$finding->id}/evidence", [
        'type' => 'text',
        'content' => $textContent,
    ]);
    $response->assertForbidden();
});

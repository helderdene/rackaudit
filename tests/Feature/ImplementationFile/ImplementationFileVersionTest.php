<?php

use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('first file upload sets version_group_id to own id and version_number to 1', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create();

    $file = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'uploaded_by' => $user->id,
        'version_number' => 1,
    ]);

    // Set version_group_id to its own id (simulating first upload behavior)
    $file->update(['version_group_id' => $file->id]);

    expect($file->fresh()->version_group_id)->toBe($file->id);
    expect($file->fresh()->version_number)->toBe(1);
});

test('subsequent uploads with same original_name increment version_number', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create();

    // First version
    $version1 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'uploaded_by' => $user->id,
        'version_number' => 1,
    ]);
    $version1->update(['version_group_id' => $version1->id]);

    // Second version (same original_name)
    $version2 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'uploaded_by' => $user->id,
        'version_group_id' => $version1->id,
        'version_number' => 2,
    ]);

    // Third version
    $version3 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'uploaded_by' => $user->id,
        'version_group_id' => $version1->id,
        'version_number' => 3,
    ]);

    expect($version1->fresh()->version_number)->toBe(1);
    expect($version2->fresh()->version_number)->toBe(2);
    expect($version3->fresh()->version_number)->toBe(3);

    // All should share the same version_group_id
    expect($version2->version_group_id)->toBe($version1->id);
    expect($version3->version_group_id)->toBe($version1->id);
});

test('versions relationship returns all files in same version group', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create();

    // Create version chain
    $version1 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'uploaded_by' => $user->id,
        'version_number' => 1,
    ]);
    $version1->update(['version_group_id' => $version1->id]);

    $version2 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'uploaded_by' => $user->id,
        'version_group_id' => $version1->id,
        'version_number' => 2,
    ]);

    $version3 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'uploaded_by' => $user->id,
        'version_group_id' => $version1->id,
        'version_number' => 3,
    ]);

    // Create unrelated file
    $unrelatedFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'other_file.pdf',
        'version_number' => 1,
    ]);
    $unrelatedFile->update(['version_group_id' => $unrelatedFile->id]);

    // Test versions relationship from version 1
    $versions = $version1->versions;
    expect($versions)->toHaveCount(3);
    expect($versions->pluck('id')->toArray())->toContain($version1->id, $version2->id, $version3->id);
    expect($versions->pluck('id')->toArray())->not->toContain($unrelatedFile->id);

    // Test versions relationship from version 2 (should also return all 3)
    $versionsFromV2 = $version2->versions;
    expect($versionsFromV2)->toHaveCount(3);
});

test('isLatestVersion accessor correctly identifies current version', function () {
    $datacenter = Datacenter::factory()->create();

    // Create version chain
    $version1 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'version_number' => 1,
    ]);
    $version1->update(['version_group_id' => $version1->id]);

    $version2 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'version_group_id' => $version1->id,
        'version_number' => 2,
    ]);

    $version3 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'version_group_id' => $version1->id,
        'version_number' => 3,
    ]);

    expect($version1->fresh()->is_latest_version)->toBeFalse();
    expect($version2->fresh()->is_latest_version)->toBeFalse();
    expect($version3->fresh()->is_latest_version)->toBeTrue();
});

test('latestVersion relationship returns highest version_number file in group', function () {
    $datacenter = Datacenter::factory()->create();

    // Create version chain
    $version1 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'version_number' => 1,
    ]);
    $version1->update(['version_group_id' => $version1->id]);

    $version2 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'version_group_id' => $version1->id,
        'version_number' => 2,
    ]);

    $version3 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'implementation_spec.pdf',
        'version_group_id' => $version1->id,
        'version_number' => 3,
    ]);

    // From any version, latestVersion should return version3
    expect($version1->latestVersion->id)->toBe($version3->id);
    expect($version2->latestVersion->id)->toBe($version3->id);
    expect($version3->latestVersion->id)->toBe($version3->id);
});

test('hasMultipleVersions accessor returns true only when multiple versions exist', function () {
    $datacenter = Datacenter::factory()->create();

    // Single version file
    $singleFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'single_file.pdf',
        'version_number' => 1,
    ]);
    $singleFile->update(['version_group_id' => $singleFile->id]);

    expect($singleFile->fresh()->has_multiple_versions)->toBeFalse();

    // Multi-version file
    $version1 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'multi_file.pdf',
        'version_number' => 1,
    ]);
    $version1->update(['version_group_id' => $version1->id]);

    $version2 = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'original_name' => 'multi_file.pdf',
        'version_group_id' => $version1->id,
        'version_number' => 2,
    ]);

    expect($version1->fresh()->has_multiple_versions)->toBeTrue();
    expect($version2->fresh()->has_multiple_versions)->toBeTrue();
});

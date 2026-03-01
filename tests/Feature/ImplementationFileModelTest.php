<?php

use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('implementation file can be created with valid attributes', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create();

    $file = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'file_name' => 'a1b2c3d4-5678-9abc-def0-123456789abc.pdf',
        'original_name' => 'implementation_spec.pdf',
        'description' => 'Test implementation specification document',
        'file_path' => 'implementation-files/'.$datacenter->id.'/a1b2c3d4-5678-9abc-def0-123456789abc.pdf',
        'file_size' => 1048576,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $user->id,
    ]);

    expect($file->datacenter_id)->toBe($datacenter->id);
    expect($file->file_name)->toBe('a1b2c3d4-5678-9abc-def0-123456789abc.pdf');
    expect($file->original_name)->toBe('implementation_spec.pdf');
    expect($file->description)->toBe('Test implementation specification document');
    expect($file->file_size)->toBe(1048576);
    expect($file->mime_type)->toBe('application/pdf');
    expect($file->uploaded_by)->toBe($user->id);
});

test('implementation file belongs to a datacenter', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $file = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    expect($file->datacenter)->toBeInstanceOf(Datacenter::class);
    expect($file->datacenter->id)->toBe($datacenter->id);
    expect($file->datacenter->name)->toBe('Test DC');
});

test('implementation file belongs to uploader user', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $file = ImplementationFile::factory()->create([
        'uploaded_by' => $user->id,
    ]);

    expect($file->uploader)->toBeInstanceOf(User::class);
    expect($file->uploader->id)->toBe($user->id);
    expect($file->uploader->name)->toBe('John Doe');
});

test('implementation file formatted file size accessor converts bytes correctly', function () {
    // Test bytes (less than 1 KB)
    $fileBytes = ImplementationFile::factory()->create(['file_size' => 500]);
    expect($fileBytes->formatted_file_size)->toBe('500 B');

    // Test kilobytes (1 KB = 1024 bytes)
    $fileKb = ImplementationFile::factory()->create(['file_size' => 2560]);
    expect($fileKb->formatted_file_size)->toBe('2.5 KB');

    // Test megabytes (1 MB = 1048576 bytes)
    $fileMb = ImplementationFile::factory()->create(['file_size' => 2621440]);
    expect($fileMb->formatted_file_size)->toBe('2.5 MB');

    // Test edge case: exactly 1 KB
    $fileExactKb = ImplementationFile::factory()->create(['file_size' => 1024]);
    expect($fileExactKb->formatted_file_size)->toBe('1 KB');

    // Test edge case: exactly 1 MB
    $fileExactMb = ImplementationFile::factory()->create(['file_size' => 1048576]);
    expect($fileExactMb->formatted_file_size)->toBe('1 MB');
});

test('implementation file file type label accessor returns human-readable type', function () {
    // Test PDF
    $pdf = ImplementationFile::factory()->pdf()->create();
    expect($pdf->file_type_label)->toBe('PDF Document');

    // Test Excel XLSX
    $xlsx = ImplementationFile::factory()->xlsx()->create();
    expect($xlsx->file_type_label)->toBe('Excel Spreadsheet');

    // Test Excel XLS
    $xls = ImplementationFile::factory()->xls()->create();
    expect($xls->file_type_label)->toBe('Excel Spreadsheet');

    // Test CSV
    $csv = ImplementationFile::factory()->csv()->create();
    expect($csv->file_type_label)->toBe('CSV File');

    // Test Word DOCX
    $docx = ImplementationFile::factory()->docx()->create();
    expect($docx->file_type_label)->toBe('Word Document');

    // Test Text
    $txt = ImplementationFile::factory()->txt()->create();
    expect($txt->file_type_label)->toBe('Text File');
});

test('implementation file supports soft deletes', function () {
    $file = ImplementationFile::factory()->create();

    expect(ImplementationFile::count())->toBe(1);

    $file->delete();

    // Record should be soft deleted
    expect(ImplementationFile::count())->toBe(0);
    expect(ImplementationFile::withTrashed()->count())->toBe(1);
    expect($file->fresh()->deleted_at)->not->toBeNull();

    // Can be restored
    $file->restore();
    expect(ImplementationFile::count())->toBe(1);
    expect($file->fresh()->deleted_at)->toBeNull();
});

test('datacenter has many implementation files', function () {
    $datacenter = Datacenter::factory()->create();

    $file1 = ImplementationFile::factory()->create(['datacenter_id' => $datacenter->id]);
    $file2 = ImplementationFile::factory()->create(['datacenter_id' => $datacenter->id]);
    $file3 = ImplementationFile::factory()->create(['datacenter_id' => $datacenter->id]);

    expect($datacenter->implementationFiles)->toHaveCount(3);
    expect($datacenter->implementationFiles->pluck('id')->toArray())
        ->toContain($file1->id, $file2->id, $file3->id);
});

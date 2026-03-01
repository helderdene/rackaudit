<?php

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Basic export class for testing
 */
class TestExport implements FromArray
{
    public function array(): array
    {
        return [
            ['name', 'city', 'country'],
            ['Datacenter 1', 'New York', 'USA'],
            ['Datacenter 2', 'London', 'UK'],
        ];
    }
}

/**
 * Basic import class for testing
 */
class TestImport implements ToArray, WithHeadingRow
{
    public array $data = [];

    public function array(array $array): void
    {
        $this->data = $array;
    }
}

it('has Laravel Excel package available', function () {
    expect(class_exists(\Maatwebsite\Excel\Excel::class))->toBeTrue();
    expect(class_exists(\Maatwebsite\Excel\Facades\Excel::class))->toBeTrue();
});

it('can access Excel configuration', function () {
    $config = config('excel');

    expect($config)->toBeArray();
    expect($config)->toHaveKey('imports');
    expect($config)->toHaveKey('exports');
    expect($config)->toHaveKey('cache');
    expect($config)->toHaveKey('temporary_files');
});

it('has correct import configuration for datacenter imports', function () {
    $importConfig = config('excel.imports');

    // Verify key settings for datacenter imports
    expect($importConfig['read_only'])->toBeTrue();
    expect($importConfig['ignore_empty'])->toBeTrue();
    expect($importConfig['heading_row']['formatter'])->toBe('slug');

    // Verify cell middleware is enabled
    expect($importConfig['cells']['middleware'])->toContain(
        \Maatwebsite\Excel\Middleware\TrimCellValue::class
    );
    expect($importConfig['cells']['middleware'])->toContain(
        \Maatwebsite\Excel\Middleware\ConvertEmptyCellValuesToNull::class
    );
});

it('has correct cache configuration for large file handling', function () {
    $cacheConfig = config('excel.cache');

    // Using batch driver for memory efficiency with large files
    expect($cacheConfig['driver'])->toBe('batch');
    expect($cacheConfig['batch']['memory_limit'])->toBeGreaterThanOrEqual(60000);
});

it('can export data to xlsx format', function () {
    Storage::fake('local');

    $export = new TestExport;
    Excel::store($export, 'test-export.xlsx', 'local');

    Storage::disk('local')->assertExists('test-export.xlsx');
});

it('can export data to csv format', function () {
    Storage::fake('local');

    $export = new TestExport;
    Excel::store($export, 'test-export.csv', 'local');

    Storage::disk('local')->assertExists('test-export.csv');
});

it('can import data from xlsx file', function () {
    // Create a temporary xlsx file for testing
    Storage::fake('local');

    $export = new TestExport;
    Excel::store($export, 'test-import.xlsx', 'local');

    $import = new TestImport;
    Excel::import($import, 'test-import.xlsx', 'local');

    expect($import->data)->toHaveCount(2);
    expect($import->data[0]['name'])->toBe('Datacenter 1');
    expect($import->data[0]['city'])->toBe('New York');
    expect($import->data[1]['name'])->toBe('Datacenter 2');
    expect($import->data[1]['country'])->toBe('UK');
});

it('has make:import and make:export artisan commands available', function () {
    $this->artisan('list')
        ->expectsOutputToContain('make:export')
        ->expectsOutputToContain('make:import');
});

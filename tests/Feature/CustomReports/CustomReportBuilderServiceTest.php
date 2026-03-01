<?php

use App\DTOs\ReportFieldConfiguration;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Enums\ReportType;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Services\CustomReportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('returns correct field configurations for each report type', function () {
    $service = app(CustomReportBuilderService::class);

    // Test Capacity report fields
    $capacityFields = $service->getAvailableFieldsForType(ReportType::Capacity);
    expect($capacityFields)->toBeArray();
    expect($capacityFields)->not->toBeEmpty();

    $capacityFieldKeys = collect($capacityFields)->pluck('key')->toArray();
    expect($capacityFieldKeys)->toContain('rack_name');
    expect($capacityFieldKeys)->toContain('datacenter_name');
    expect($capacityFieldKeys)->toContain('u_height');
    expect($capacityFieldKeys)->toContain('utilization_percent');
    expect($capacityFieldKeys)->toContain('devices_per_rack');

    // Verify field is a ReportFieldConfiguration instance
    $firstField = $capacityFields[0];
    expect($firstField)->toBeInstanceOf(ReportFieldConfiguration::class);
    expect($firstField->key)->not->toBeEmpty();
    expect($firstField->displayName)->not->toBeEmpty();
    expect($firstField->category)->not->toBeEmpty();

    // Test Assets report fields
    $assetsFields = $service->getAvailableFieldsForType(ReportType::Assets);
    $assetsFieldKeys = collect($assetsFields)->pluck('key')->toArray();
    expect($assetsFieldKeys)->toContain('asset_tag');
    expect($assetsFieldKeys)->toContain('serial_number');
    expect($assetsFieldKeys)->toContain('warranty_end_date');
    expect($assetsFieldKeys)->toContain('days_until_warranty_expiration');
    expect($assetsFieldKeys)->toContain('age_in_years');

    // Test Connections report fields
    $connectionsFields = $service->getAvailableFieldsForType(ReportType::Connections);
    $connectionsFieldKeys = collect($connectionsFields)->pluck('key')->toArray();
    expect($connectionsFieldKeys)->toContain('connection_id');
    expect($connectionsFieldKeys)->toContain('source_device');
    expect($connectionsFieldKeys)->toContain('cable_type');
    expect($connectionsFieldKeys)->toContain('port_utilization_percentage');

    // Test Audit History report fields
    $auditFields = $service->getAvailableFieldsForType(ReportType::AuditHistory);
    $auditFieldKeys = collect($auditFields)->pluck('key')->toArray();
    expect($auditFieldKeys)->toContain('audit_id');
    expect($auditFieldKeys)->toContain('finding_count');
    expect($auditFieldKeys)->toContain('days_to_resolution');
    expect($auditFieldKeys)->toContain('finding_resolution_rate');
});

test('returns calculated fields only when requesting calculated fields', function () {
    $service = app(CustomReportBuilderService::class);

    // Test Capacity calculated fields
    $capacityCalculatedFields = $service->getCalculatedFieldsForType(ReportType::Capacity);
    expect($capacityCalculatedFields)->toBeArray();

    $calculatedKeys = collect($capacityCalculatedFields)->pluck('key')->toArray();
    expect($calculatedKeys)->toContain('devices_per_rack');

    // All returned fields should be marked as calculated
    foreach ($capacityCalculatedFields as $field) {
        expect($field->isCalculated)->toBeTrue();
    }

    // Test Assets calculated fields
    $assetsCalculatedFields = $service->getCalculatedFieldsForType(ReportType::Assets);
    $assetsCalculatedKeys = collect($assetsCalculatedFields)->pluck('key')->toArray();
    expect($assetsCalculatedKeys)->toContain('days_until_warranty_expiration');
    expect($assetsCalculatedKeys)->toContain('age_in_years');

    // Test Connections calculated fields
    $connectionsCalculatedFields = $service->getCalculatedFieldsForType(ReportType::Connections);
    $connectionsCalculatedKeys = collect($connectionsCalculatedFields)->pluck('key')->toArray();
    expect($connectionsCalculatedKeys)->toContain('port_utilization_percentage');

    // Test Audit History calculated fields
    $auditCalculatedFields = $service->getCalculatedFieldsForType(ReportType::AuditHistory);
    $auditCalculatedKeys = collect($auditCalculatedFields)->pluck('key')->toArray();
    expect($auditCalculatedKeys)->toContain('days_to_resolution');
    expect($auditCalculatedKeys)->toContain('finding_resolution_rate');
});

test('computes calculated field values correctly for capacity reports', function () {
    $service = app(CustomReportBuilderService::class);

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Create a rack with 3 devices
    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    Device::factory()->count(3)->create(['rack_id' => $rack->id]);

    // Load the rack with devices relation
    $rack->load('devices');

    $calculatedValues = $service->computeCalculatedFields(
        ReportType::Capacity,
        $rack,
        ['devices_per_rack']
    );

    expect($calculatedValues)->toHaveKey('devices_per_rack');
    expect($calculatedValues['devices_per_rack'])->toBe(3);
});

test('computes calculated field values correctly for assets reports', function () {
    $service = app(CustomReportBuilderService::class);

    // Use a specific date to avoid timing edge cases
    $warrantyEndDate = now()->startOfDay()->addDays(30);
    $purchaseDate = now()->startOfDay()->subYears(2);

    // Create a device with warranty and purchase date
    $device = Device::factory()->create([
        'warranty_end_date' => $warrantyEndDate,
        'purchase_date' => $purchaseDate,
    ]);

    $calculatedValues = $service->computeCalculatedFields(
        ReportType::Assets,
        $device,
        ['days_until_warranty_expiration', 'age_in_years']
    );

    expect($calculatedValues)->toHaveKey('days_until_warranty_expiration');
    expect($calculatedValues)->toHaveKey('age_in_years');

    // Allow for a tolerance of 1 day due to time differences
    expect($calculatedValues['days_until_warranty_expiration'])->toBeGreaterThanOrEqual(29);
    expect($calculatedValues['days_until_warranty_expiration'])->toBeLessThanOrEqual(31);

    // Age should be close to 2 years
    expect($calculatedValues['age_in_years'])->toBeGreaterThanOrEqual(1.9);
    expect($calculatedValues['age_in_years'])->toBeLessThanOrEqual(2.1);

    // Test with null dates
    $deviceNoWarranty = Device::factory()->create([
        'warranty_end_date' => null,
        'purchase_date' => null,
    ]);

    $nullCalculatedValues = $service->computeCalculatedFields(
        ReportType::Assets,
        $deviceNoWarranty,
        ['days_until_warranty_expiration', 'age_in_years']
    );

    expect($nullCalculatedValues['days_until_warranty_expiration'])->toBeNull();
    expect($nullCalculatedValues['age_in_years'])->toBeNull();
});

test('builds filtered query for capacity reports with location filters', function () {
    $service = app(CustomReportBuilderService::class);

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Create racks in the test datacenter
    $rack1 = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
    ]);
    $rack2 = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
    ]);

    // Create a rack in a different datacenter
    $otherDatacenter = Datacenter::factory()->create();
    $otherRoom = Room::factory()->create(['datacenter_id' => $otherDatacenter->id]);
    $otherRow = Row::factory()->create(['room_id' => $otherRoom->id]);
    Rack::factory()->create([
        'row_id' => $otherRow->id,
        'status' => RackStatus::Active,
    ]);

    // Build query with datacenter filter
    $query = $service->buildQuery(
        ReportType::Capacity,
        ['rack_name', 'datacenter_name'],
        ['datacenter_id' => $datacenter->id],
        [],
        null
    );

    $results = $query->get();

    // Should only include racks from the filtered datacenter
    expect($results)->toHaveCount(2);
    expect($results->pluck('id')->toArray())->toContain($rack1->id);
    expect($results->pluck('id')->toArray())->toContain($rack2->id);
});

test('returns correct filter options for each report type', function () {
    $service = app(CustomReportBuilderService::class);

    // Test Capacity filters
    $capacityFilters = $service->getAvailableFiltersForType(ReportType::Capacity);
    expect($capacityFilters)->toHaveKey('datacenter_id');
    expect($capacityFilters)->toHaveKey('room_id');
    expect($capacityFilters)->toHaveKey('row_id');
    expect($capacityFilters)->toHaveKey('utilization_threshold');

    // Test Assets filters
    $assetsFilters = $service->getAvailableFiltersForType(ReportType::Assets);
    expect($assetsFilters)->toHaveKey('datacenter_id');
    expect($assetsFilters)->toHaveKey('device_type_id');
    expect($assetsFilters)->toHaveKey('lifecycle_status');
    expect($assetsFilters)->toHaveKey('manufacturer');
    expect($assetsFilters)->toHaveKey('warranty_start');
    expect($assetsFilters)->toHaveKey('warranty_end');

    // Verify lifecycle_status has options
    expect($assetsFilters['lifecycle_status'])->toHaveKey('options');
    expect($assetsFilters['lifecycle_status']['options'])->not->toBeEmpty();

    // Test Connections filters
    $connectionsFilters = $service->getAvailableFiltersForType(ReportType::Connections);
    expect($connectionsFilters)->toHaveKey('datacenter_id');
    expect($connectionsFilters)->toHaveKey('cable_type');
    expect($connectionsFilters)->toHaveKey('connection_status');

    // Verify cable_type has options
    expect($connectionsFilters['cable_type'])->toHaveKey('options');

    // Test Audit History filters
    $auditFilters = $service->getAvailableFiltersForType(ReportType::AuditHistory);
    expect($auditFilters)->toHaveKey('datacenter_id');
    expect($auditFilters)->toHaveKey('start_date');
    expect($auditFilters)->toHaveKey('end_date');
    expect($auditFilters)->toHaveKey('audit_type');
    expect($auditFilters)->toHaveKey('finding_severity');
});

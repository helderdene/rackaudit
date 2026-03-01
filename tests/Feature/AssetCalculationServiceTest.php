<?php

use App\Enums\DeviceLifecycleStatus;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Services\AssetCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('warranty status categorization returns correct counts for active, expiring soon, expired, and unknown', function () {
    $service = app(AssetCalculationService::class);

    // Create device with active warranty (more than 30 days from now)
    Device::factory()->create([
        'warranty_end_date' => now()->addDays(60),
    ]);

    // Create device with expiring soon warranty (within 30 days)
    Device::factory()->create([
        'warranty_end_date' => now()->addDays(15),
    ]);

    // Create device with expired warranty (past date)
    Device::factory()->create([
        'warranty_end_date' => now()->subDays(10),
    ]);

    // Create device with unknown warranty (null)
    Device::factory()->create([
        'warranty_end_date' => null,
    ]);

    $query = Device::query();
    $result = $service->getWarrantyStatusCounts($query);

    expect($result)->toHaveKeys(['active', 'expiring_soon', 'expired', 'unknown']);
    expect($result['active'])->toBe(1);
    expect($result['expiring_soon'])->toBe(1);
    expect($result['expired'])->toBe(1);
    expect($result['unknown'])->toBe(1);
});

test('lifecycle distribution returns counts for all 7 DeviceLifecycleStatus values', function () {
    $service = app(AssetCalculationService::class);

    // Create devices for each lifecycle status
    Device::factory()->ordered()->create();
    Device::factory()->received()->create();
    Device::factory()->inStock()->count(2)->create();
    Device::factory()->deployed()->create();
    Device::factory()->maintenance()->create();
    Device::factory()->decommissioned()->create();
    Device::factory()->disposed()->create();

    $query = Device::query();
    $result = $service->getLifecycleDistribution($query);

    // Should have entries for all 7 statuses
    expect($result)->toHaveCount(7);

    // Verify structure and counts
    $statusCounts = collect($result)->pluck('count', 'status')->toArray();
    expect($statusCounts['ordered'])->toBe(1);
    expect($statusCounts['received'])->toBe(1);
    expect($statusCounts['in_stock'])->toBe(2);
    expect($statusCounts['deployed'])->toBe(1);
    expect($statusCounts['maintenance'])->toBe(1);
    expect($statusCounts['decommissioned'])->toBe(1);
    expect($statusCounts['disposed'])->toBe(1);

    // Verify each entry has correct structure
    foreach ($result as $entry) {
        expect($entry)->toHaveKeys(['status', 'label', 'count', 'percentage']);
    }
});

test('device counts by type groups correctly by DeviceType relationship', function () {
    $service = app(AssetCalculationService::class);

    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);
    $storageType = DeviceType::factory()->create(['name' => 'Storage']);

    Device::factory()->count(3)->create(['device_type_id' => $serverType->id]);
    Device::factory()->count(2)->create(['device_type_id' => $switchType->id]);
    Device::factory()->count(1)->create(['device_type_id' => $storageType->id]);

    $query = Device::query();
    $result = $service->getDeviceCountsByType($query);

    expect($result)->toHaveCount(3);

    $countsByName = collect($result)->pluck('count', 'name')->toArray();
    expect($countsByName['Server'])->toBe(3);
    expect($countsByName['Switch'])->toBe(2);
    expect($countsByName['Storage'])->toBe(1);
});

test('device counts by manufacturer groups correctly by manufacturer field', function () {
    $service = app(AssetCalculationService::class);

    Device::factory()->count(4)->create(['manufacturer' => 'Dell']);
    Device::factory()->count(2)->create(['manufacturer' => 'HP']);
    Device::factory()->count(1)->create(['manufacturer' => 'Cisco']);
    Device::factory()->count(1)->create(['manufacturer' => null]); // Device with no manufacturer

    $query = Device::query();
    $result = $service->getDeviceCountsByManufacturer($query);

    // Should have 4 groups (3 manufacturers + 1 for null/unknown)
    expect($result)->toHaveCount(4);

    $countsByName = collect($result)->pluck('count', 'name')->toArray();
    expect($countsByName['Dell'])->toBe(4);
    expect($countsByName['HP'])->toBe(2);
    expect($countsByName['Cisco'])->toBe(1);
    expect($countsByName['Unknown'])->toBe(1);
});

test('filter application works for datacenter, room, device type, lifecycle status, and manufacturer', function () {
    $service = app(AssetCalculationService::class);

    // Create full hierarchy for datacenter 1
    $datacenter1 = Datacenter::factory()->create();
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);

    // Create full hierarchy for datacenter 2
    $datacenter2 = Datacenter::factory()->create();
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);

    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);

    // Create devices in datacenter 1
    Device::factory()->create([
        'rack_id' => $rack1->id,
        'device_type_id' => $serverType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'manufacturer' => 'Dell',
    ]);
    Device::factory()->create([
        'rack_id' => $rack1->id,
        'device_type_id' => $switchType->id,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'manufacturer' => 'Cisco',
    ]);

    // Create devices in datacenter 2
    Device::factory()->count(3)->create([
        'rack_id' => $rack2->id,
        'device_type_id' => $serverType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'manufacturer' => 'HP',
    ]);

    // Test datacenter filter
    $query = $service->buildFilteredDeviceQuery($datacenter1->id);
    expect($query->count())->toBe(2);

    // Test room filter
    $query = $service->buildFilteredDeviceQuery($datacenter1->id, $room1->id);
    expect($query->count())->toBe(2);

    // Test device type filter
    $query = $service->buildFilteredDeviceQuery(null, null, $serverType->id);
    expect($query->count())->toBe(4); // 1 in DC1 + 3 in DC2

    // Test lifecycle status filter
    $query = $service->buildFilteredDeviceQuery(null, null, null, DeviceLifecycleStatus::Deployed->value);
    expect($query->count())->toBe(4); // All deployed

    // Test manufacturer filter
    $query = $service->buildFilteredDeviceQuery(null, null, null, null, 'Dell');
    expect($query->count())->toBe(1);

    // Test combined filters
    $query = $service->buildFilteredDeviceQuery(
        $datacenter1->id,
        null,
        $serverType->id,
        DeviceLifecycleStatus::Deployed->value,
        'Dell'
    );
    expect($query->count())->toBe(1);
});

test('date range filtering for warranty expiration works correctly', function () {
    $service = app(AssetCalculationService::class);

    // Create devices with various warranty end dates
    Device::factory()->create([
        'warranty_end_date' => now()->addDays(10),
    ]);
    Device::factory()->create([
        'warranty_end_date' => now()->addDays(30),
    ]);
    Device::factory()->create([
        'warranty_end_date' => now()->addDays(60),
    ]);
    Device::factory()->create([
        'warranty_end_date' => now()->addDays(90),
    ]);
    Device::factory()->create([
        'warranty_end_date' => null,
    ]);

    // Filter devices with warranty ending in the next 45 days
    $startDate = now()->format('Y-m-d');
    $endDate = now()->addDays(45)->format('Y-m-d');

    $query = $service->buildFilteredDeviceQuery(
        null, // datacenter_id
        null, // room_id
        null, // device_type_id
        null, // lifecycle_status
        null, // manufacturer
        $startDate, // warranty_start
        $endDate // warranty_end
    );

    expect($query->count())->toBe(2); // 10 days and 30 days devices

    // Filter devices with warranty ending between 30 and 90 days
    $startDate = now()->addDays(25)->format('Y-m-d');
    $endDate = now()->addDays(95)->format('Y-m-d');

    $query = $service->buildFilteredDeviceQuery(
        null,
        null,
        null,
        null,
        null,
        $startDate,
        $endDate
    );

    expect($query->count())->toBe(3); // 30, 60, and 90 days devices
});

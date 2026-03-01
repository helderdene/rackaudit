<?php

use App\Models\Datacenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('datacenter can be created with required fields', function () {
    $datacenter = Datacenter::factory()->create([
        'name' => 'Test Data Center',
        'address_line_1' => '123 Main Street',
        'city' => 'New York',
        'state_province' => 'NY',
        'postal_code' => '10001',
        'country' => 'United States',
        'primary_contact_name' => 'John Doe',
        'primary_contact_email' => 'john@example.com',
        'primary_contact_phone' => '+1-555-123-4567',
    ]);

    expect($datacenter->name)->toBe('Test Data Center');
    expect($datacenter->address_line_1)->toBe('123 Main Street');
    expect($datacenter->city)->toBe('New York');
    expect($datacenter->state_province)->toBe('NY');
    expect($datacenter->postal_code)->toBe('10001');
    expect($datacenter->country)->toBe('United States');
    expect($datacenter->primary_contact_name)->toBe('John Doe');
    expect($datacenter->primary_contact_email)->toBe('john@example.com');
    expect($datacenter->primary_contact_phone)->toBe('+1-555-123-4567');
});

test('datacenter handles optional fields correctly', function () {
    $datacenter = Datacenter::factory()->create([
        'address_line_2' => null,
        'company_name' => null,
        'secondary_contact_name' => null,
        'secondary_contact_email' => null,
        'secondary_contact_phone' => null,
    ]);

    expect($datacenter->address_line_2)->toBeNull();
    expect($datacenter->company_name)->toBeNull();
    expect($datacenter->secondary_contact_name)->toBeNull();
    expect($datacenter->secondary_contact_email)->toBeNull();
    expect($datacenter->secondary_contact_phone)->toBeNull();

    // Now create one with optional fields filled
    $datacenterWithOptional = Datacenter::factory()->withSecondaryContact()->create([
        'address_line_2' => 'Suite 500',
        'company_name' => 'Acme Corp',
    ]);

    expect($datacenterWithOptional->address_line_2)->toBe('Suite 500');
    expect($datacenterWithOptional->company_name)->toBe('Acme Corp');
    expect($datacenterWithOptional->secondary_contact_name)->not->toBeNull();
    expect($datacenterWithOptional->secondary_contact_email)->not->toBeNull();
    expect($datacenterWithOptional->secondary_contact_phone)->not->toBeNull();
});

test('datacenter formattedAddress accessor returns correct format', function () {
    $datacenter = Datacenter::factory()->create([
        'address_line_1' => '123 Main Street',
        'address_line_2' => 'Suite 500',
        'city' => 'New York',
        'state_province' => 'NY',
        'postal_code' => '10001',
        'country' => 'United States',
    ]);

    $expectedAddress = "123 Main Street\nSuite 500\nNew York, NY 10001\nUnited States";
    expect($datacenter->formattedAddress)->toBe($expectedAddress);

    // Test without address_line_2
    $datacenterNoSuite = Datacenter::factory()->create([
        'address_line_1' => '456 Oak Avenue',
        'address_line_2' => null,
        'city' => 'Los Angeles',
        'state_province' => 'CA',
        'postal_code' => '90001',
        'country' => 'United States',
    ]);

    $expectedAddressNoSuite = "456 Oak Avenue\nLos Angeles, CA 90001\nUnited States";
    expect($datacenterNoSuite->formattedAddress)->toBe($expectedAddressNoSuite);
});

test('datacenter formattedLocation accessor returns city and country', function () {
    $datacenter = Datacenter::factory()->create([
        'city' => 'Tokyo',
        'country' => 'Japan',
    ]);

    expect($datacenter->formattedLocation)->toBe('Tokyo, Japan');
});

test('datacenter users relationship works correctly', function () {
    $datacenter = Datacenter::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $datacenter->users()->attach([$user1->id, $user2->id]);

    expect($datacenter->users)->toHaveCount(2);
    expect($datacenter->users()->get())->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($datacenter->users->pluck('id')->toArray())->toContain($user1->id, $user2->id);
});

test('datacenter floor_plan_path field stores file path correctly', function () {
    $datacenter = Datacenter::factory()->create([
        'floor_plan_path' => null,
    ]);

    expect($datacenter->floor_plan_path)->toBeNull();

    // Update with a file path
    $datacenter->update(['floor_plan_path' => 'floor-plans/datacenter_1_1735000000.png']);

    expect($datacenter->fresh()->floor_plan_path)->toBe('floor-plans/datacenter_1_1735000000.png');

    // Test factory state
    $datacenterWithFloorPlan = Datacenter::factory()->withFloorPlan()->create();

    expect($datacenterWithFloorPlan->floor_plan_path)->not->toBeNull();
    expect($datacenterWithFloorPlan->floor_plan_path)->toContain('floor-plans/');
});

<?php

use App\Models\FindingCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// Test 1: Store action creates new category with is_default=false
test('store action creates new category with is_default false', function () {
    $user = User::factory()->create();
    $user->assignRole('Operator');

    $response = $this->actingAs($user)->postJson('/finding-categories', [
        'name' => 'Custom Category',
        'description' => 'A custom category created by user',
    ]);

    $response->assertCreated()
        ->assertJsonFragment([
            'name' => 'Custom Category',
            'description' => 'A custom category created by user',
            'is_default' => false,
        ]);

    $this->assertDatabaseHas('finding_categories', [
        'name' => 'Custom Category',
        'description' => 'A custom category created by user',
        'is_default' => false,
    ]);
});

// Test 2: Store validation rejects duplicate category names
test('store validation rejects duplicate category names', function () {
    $user = User::factory()->create();
    $user->assignRole('Operator');

    // Create an existing category
    FindingCategory::factory()->create(['name' => 'Existing Category']);

    $response = $this->actingAs($user)->postJson('/finding-categories', [
        'name' => 'Existing Category',
        'description' => 'Trying to create duplicate',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    // Verify only one category exists with that name
    expect(FindingCategory::where('name', 'Existing Category')->count())->toBe(1);
});

// Test 3: Index action returns all categories (defaults first, then custom sorted alphabetically)
test('index action returns all categories with defaults first then custom sorted alphabetically', function () {
    $user = User::factory()->create();
    $user->assignRole('Operator');

    // Create default categories (simulating seeded categories)
    $defaultA = FindingCategory::factory()->create([
        'name' => 'Missing',
        'is_default' => true,
    ]);
    $defaultB = FindingCategory::factory()->create([
        'name' => 'Unexpected',
        'is_default' => true,
    ]);

    // Create custom categories (not in alphabetical order)
    $customZ = FindingCategory::factory()->create([
        'name' => 'Zebra Issue',
        'is_default' => false,
    ]);
    $customA = FindingCategory::factory()->create([
        'name' => 'Alpha Problem',
        'is_default' => false,
    ]);

    $response = $this->actingAs($user)->getJson('/finding-categories');

    $response->assertOk()
        ->assertJsonCount(4, 'data');

    $data = $response->json('data');

    // First items should be default categories (sorted alphabetically)
    expect($data[0]['is_default'])->toBeTrue();
    expect($data[1]['is_default'])->toBeTrue();

    // Then custom categories sorted alphabetically
    expect($data[2]['is_default'])->toBeFalse();
    expect($data[2]['name'])->toBe('Alpha Problem');
    expect($data[3]['is_default'])->toBeFalse();
    expect($data[3]['name'])->toBe('Zebra Issue');
});

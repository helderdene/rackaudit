<?php

/**
 * Strategic tests to fill coverage gaps for the Datacenter Management feature.
 *
 * These tests cover:
 * - End-to-end workflows with floor plan upload
 * - Authorization scenarios for all roles
 * - File validation (type and size)
 * - Floor plan replacement and removal
 * - IT Manager role access
 */

use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: End-to-end test - Create datacenter with floor plan upload
 */
test('can create datacenter with floor plan upload', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $floorPlan = UploadedFile::fake()->image('floor-plan.png', 800, 600);

    $datacenterData = [
        'name' => 'New Datacenter With Floor Plan',
        'address_line_1' => '123 Main Street',
        'city' => 'San Francisco',
        'state_province' => 'CA',
        'postal_code' => '94105',
        'country' => 'United States',
        'primary_contact_name' => 'John Doe',
        'primary_contact_email' => 'john@example.com',
        'primary_contact_phone' => '+1-555-123-4567',
        'floor_plan' => $floorPlan,
    ];

    $response = $this->actingAs($admin)
        ->post('/datacenters', $datacenterData);

    $response->assertRedirect('/datacenters')
        ->assertSessionHas('success');

    // Verify datacenter was created
    $datacenter = Datacenter::where('name', 'New Datacenter With Floor Plan')->first();
    expect($datacenter)->not->toBeNull();
    expect($datacenter->floor_plan_path)->not->toBeNull();

    // Verify floor plan file was stored
    Storage::disk('public')->assertExists($datacenter->floor_plan_path);
});

/**
 * Test 2: End-to-end test - Edit datacenter and replace floor plan
 */
test('can edit datacenter and replace floor plan', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a datacenter with an initial floor plan
    $initialFloorPlan = UploadedFile::fake()->image('initial-plan.png', 800, 600);
    $initialPath = $initialFloorPlan->storeAs('floor-plans', 'initial_plan.png', 'public');

    $datacenter = Datacenter::factory()->create([
        'floor_plan_path' => $initialPath,
    ]);

    // Store the file manually since we're using a fake storage
    Storage::disk('public')->put($initialPath, 'initial content');

    // Verify initial file exists
    Storage::disk('public')->assertExists($initialPath);

    // Create a new floor plan to replace the existing one
    $newFloorPlan = UploadedFile::fake()->image('new-plan.jpg', 1024, 768);

    $response = $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}", [
            'name' => $datacenter->name,
            'address_line_1' => $datacenter->address_line_1,
            'city' => $datacenter->city,
            'state_province' => $datacenter->state_province,
            'postal_code' => $datacenter->postal_code,
            'country' => $datacenter->country,
            'primary_contact_name' => $datacenter->primary_contact_name,
            'primary_contact_email' => $datacenter->primary_contact_email,
            'primary_contact_phone' => $datacenter->primary_contact_phone,
            'floor_plan' => $newFloorPlan,
        ]);

    $response->assertRedirect('/datacenters')
        ->assertSessionHas('success');

    $datacenter->refresh();

    // Verify floor plan was replaced
    expect($datacenter->floor_plan_path)->not->toBe($initialPath);
    expect($datacenter->floor_plan_path)->toContain('.jpg');

    // Verify new file exists
    Storage::disk('public')->assertExists($datacenter->floor_plan_path);

    // Verify old file was deleted
    Storage::disk('public')->assertMissing($initialPath);
});

/**
 * Test 3: Authorization test - Non-admin cannot access create page
 */
test('operator cannot access datacenter create page', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $response = $this->actingAs($operator)->get('/datacenters/create');

    $response->assertForbidden();
});

/**
 * Test 4: Authorization test - Non-admin cannot access edit page
 */
test('viewer cannot access datacenter edit page', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $datacenter = Datacenter::factory()->create();

    // Assign viewer to the datacenter so they can at least view it
    $viewer->datacenters()->attach($datacenter->id);

    $response = $this->actingAs($viewer)->get("/datacenters/{$datacenter->id}/edit");

    $response->assertForbidden();
});

/**
 * Test 5: Validation test - File type rejection for floor plan
 */
test('floor plan upload rejects invalid file types', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Try to upload a text file (not allowed)
    $invalidFile = UploadedFile::fake()->create('document.txt', 100);

    $response = $this->actingAs($admin)
        ->post('/datacenters', [
            'name' => 'Test Datacenter',
            'address_line_1' => '123 Main St',
            'city' => 'Test City',
            'state_province' => 'TS',
            'postal_code' => '12345',
            'country' => 'USA',
            'primary_contact_name' => 'Test Contact',
            'primary_contact_email' => 'test@example.com',
            'primary_contact_phone' => '+1-555-123-4567',
            'floor_plan' => $invalidFile,
        ]);

    $response->assertSessionHasErrors(['floor_plan']);
});

/**
 * Test 6: Validation test - File size rejection for floor plan (over 10MB)
 */
test('floor plan upload rejects files over 10MB', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a file larger than 10MB (10240KB)
    $largeFile = UploadedFile::fake()->create('large-plan.png', 11000); // 11MB

    $response = $this->actingAs($admin)
        ->post('/datacenters', [
            'name' => 'Test Datacenter',
            'address_line_1' => '123 Main St',
            'city' => 'Test City',
            'state_province' => 'TS',
            'postal_code' => '12345',
            'country' => 'USA',
            'primary_contact_name' => 'Test Contact',
            'primary_contact_email' => 'test@example.com',
            'primary_contact_phone' => '+1-555-123-4567',
            'floor_plan' => $largeFile,
        ]);

    $response->assertSessionHasErrors(['floor_plan']);
});

/**
 * Test 7: Floor plan removal functionality
 */
test('can remove floor plan from datacenter', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a datacenter with a floor plan
    $floorPlan = UploadedFile::fake()->image('plan.png', 800, 600);
    $floorPlanPath = $floorPlan->storeAs('floor-plans', 'datacenter_plan.png', 'public');

    $datacenter = Datacenter::factory()->create([
        'floor_plan_path' => $floorPlanPath,
    ]);

    // Manually store the file in the fake storage
    Storage::disk('public')->put($floorPlanPath, 'content');
    Storage::disk('public')->assertExists($floorPlanPath);

    // Update datacenter with remove_floor_plan flag
    $response = $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}", [
            'name' => $datacenter->name,
            'address_line_1' => $datacenter->address_line_1,
            'city' => $datacenter->city,
            'state_province' => $datacenter->state_province,
            'postal_code' => $datacenter->postal_code,
            'country' => $datacenter->country,
            'primary_contact_name' => $datacenter->primary_contact_name,
            'primary_contact_email' => $datacenter->primary_contact_email,
            'primary_contact_phone' => $datacenter->primary_contact_phone,
            'remove_floor_plan' => true,
        ]);

    $response->assertRedirect('/datacenters');

    $datacenter->refresh();

    // Verify floor plan was removed
    expect($datacenter->floor_plan_path)->toBeNull();

    // Verify file was deleted from storage
    Storage::disk('public')->assertMissing($floorPlanPath);
});

/**
 * Test 8: IT Manager role has same access as Administrator
 */
test('IT Manager can create update and delete datacenters', function () {
    Storage::fake('public');

    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    // Test create
    $datacenterData = [
        'name' => 'IT Manager Created DC',
        'address_line_1' => '123 Tech Street',
        'city' => 'Austin',
        'state_province' => 'TX',
        'postal_code' => '78701',
        'country' => 'United States',
        'primary_contact_name' => 'IT Contact',
        'primary_contact_email' => 'it@example.com',
        'primary_contact_phone' => '+1-512-555-1234',
    ];

    $response = $this->actingAs($itManager)
        ->post('/datacenters', $datacenterData);

    $response->assertRedirect('/datacenters');

    $datacenter = Datacenter::where('name', 'IT Manager Created DC')->first();
    expect($datacenter)->not->toBeNull();

    // Test update
    $response = $this->actingAs($itManager)
        ->put("/datacenters/{$datacenter->id}", array_merge($datacenterData, [
            'name' => 'IT Manager Updated DC',
        ]));

    $response->assertRedirect('/datacenters');

    $datacenter->refresh();
    expect($datacenter->name)->toBe('IT Manager Updated DC');

    // Test delete
    $response = $this->actingAs($itManager)
        ->delete("/datacenters/{$datacenter->id}");

    $response->assertRedirect('/datacenters');
    expect(Datacenter::find($datacenter->id))->toBeNull();
});

/**
 * Test 9: Non-admin user cannot view unassigned datacenter show page
 */
test('operator cannot view show page of unassigned datacenter', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Create two datacenters
    $assignedDc = Datacenter::factory()->create(['name' => 'Assigned DC']);
    $unassignedDc = Datacenter::factory()->create(['name' => 'Unassigned DC']);

    // Assign operator to only one datacenter
    $operator->datacenters()->attach($assignedDc->id);

    // Operator can view assigned datacenter
    $response = $this->actingAs($operator)->get("/datacenters/{$assignedDc->id}");
    $response->assertOk();

    // Operator cannot view unassigned datacenter
    $response = $this->actingAs($operator)->get("/datacenters/{$unassignedDc->id}");
    $response->assertForbidden();
});

/**
 * Test 10: Floor plan accepts all valid file types (PNG, JPG, JPEG, PDF)
 */
test('floor plan accepts all valid file types', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $validFiles = [
        'floor-plan.png' => 'png',
        'floor-plan.jpg' => 'jpg',
        'floor-plan.jpeg' => 'jpeg',
        'floor-plan.pdf' => 'pdf',
    ];

    foreach ($validFiles as $filename => $extension) {
        $file = $extension === 'pdf'
            ? UploadedFile::fake()->create($filename, 500, 'application/pdf')
            : UploadedFile::fake()->image($filename, 800, 600);

        $response = $this->actingAs($admin)
            ->post('/datacenters', [
                'name' => "Datacenter with {$extension} file",
                'address_line_1' => '123 Main St',
                'city' => 'Test City',
                'state_province' => 'TS',
                'postal_code' => '12345',
                'country' => 'USA',
                'primary_contact_name' => 'Test Contact',
                'primary_contact_email' => 'test@example.com',
                'primary_contact_phone' => '+1-555-123-4567',
                'floor_plan' => $file,
            ]);

        $response->assertRedirect('/datacenters');

        $datacenter = Datacenter::where('name', "Datacenter with {$extension} file")->first();
        expect($datacenter)->not->toBeNull();
        expect($datacenter->floor_plan_path)->toContain(".{$extension}");

        Storage::disk('public')->assertExists($datacenter->floor_plan_path);
    }
});

<?php

use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('Auditor');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create datacenters for testing
    $this->datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $this->datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);

    // Assign datacenter access to non-admin users
    $this->operator->datacenters()->attach($this->datacenter1);
    $this->auditor->datacenters()->attach($this->datacenter1);
});

/**
 * Test 1: Dashboard route accessible to Auditor, IT Manager, and Administrator roles
 */
test('dashboard route is accessible to authorized roles', function (string $role, User $user) {
    $response = $this->actingAs($user)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
        );
})->with([
    'Administrator' => fn () => ['Administrator', test()->admin],
    'IT Manager' => fn () => ['IT Manager', test()->itManager],
    'Auditor' => fn () => ['Auditor', test()->auditor],
]);

/**
 * Test 2: Dashboard route forbidden for Operator role
 */
test('dashboard route is forbidden for operator role', function () {
    $operatorResponse = $this->actingAs($this->operator)->get('/audits/dashboard');
    $operatorResponse->assertForbidden();
});

/**
 * Test 3: Dashboard route redirects unauthenticated users to login
 */
test('dashboard route redirects unauthenticated users to login', function () {
    $guestResponse = $this->get('/audits/dashboard');
    $guestResponse->assertRedirect(route('login'));
});

/**
 * Test 4: Datacenter filtering applies correctly to metrics
 */
test('datacenter filtering applies correctly to metrics', function () {
    // Create audits in different datacenters
    Audit::factory()->create([
        'name' => 'DC1 Audit',
        'datacenter_id' => $this->datacenter1->id,
    ]);
    Audit::factory()->create([
        'name' => 'DC2 Audit',
        'datacenter_id' => $this->datacenter2->id,
    ]);

    // Request dashboard with datacenter filter
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id=' . $this->datacenter1->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.datacenter_id', (string) $this->datacenter1->id)
        );
});

/**
 * Test 5: Time period filtering applies correctly to metrics
 */
test('time period filtering applies correctly to metrics', function () {
    // Create audits at different times
    Audit::factory()->create([
        'name' => 'Recent Audit',
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(10),
    ]);
    Audit::factory()->create([
        'name' => 'Old Audit',
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(60),
    ]);

    // Request dashboard with time period filter (last 30 days)
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?time_period=30_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.time_period', '30_days')
        );
});

/**
 * Test 6: Query parameter persistence in response
 */
test('query parameters are persisted in response', function () {
    $response = $this->actingAs($this->itManager)
        ->get('/audits/dashboard?datacenter_id=' . $this->datacenter1->id . '&time_period=90_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.datacenter_id', (string) $this->datacenter1->id)
            ->where('filters.time_period', '90_days')
        );
});

/**
 * Test 7: Dashboard route is named correctly
 */
test('dashboard route is named audits.dashboard', function () {
    $url = route('audits.dashboard');

    expect($url)->toEndWith('/audits/dashboard');

    $response = $this->actingAs($this->admin)->get($url);
    $response->assertOk();
});

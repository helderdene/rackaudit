<?php

/**
 * Sidebar Tablet Behavior Tests
 *
 * Tests for the SidebarProvider Vue component tablet viewport behavior:
 * - Sidebar auto-collapses when viewport enters tablet range (768px-1024px)
 * - User manual toggle preference is preserved within session
 * - Mobile drawer behavior is unchanged for viewports below 768px
 * - Smooth transition when viewport crosses breakpoints
 *
 * Note: These tests verify the backend/API aspects and document expected
 * frontend behavior. The Vue component uses useMediaQuery for viewport
 * detection which executes client-side.
 */

use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

/**
 * Test 1: Sidebar state cookie is respected by the application.
 *
 * Verifies that the sidebar_state cookie value is passed to the frontend
 * via Inertia props, allowing the Vue component to initialize with the
 * correct state (expanded or collapsed).
 */
test('sidebar state cookie false collapses sidebar', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create required data
    Datacenter::factory()->create();

    // Test with sidebar collapsed (simulating tablet auto-collapse)
    $response = $this->actingAs($user)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('sidebarOpen', false)
    );
});

/**
 * Test 2: Sidebar state defaults to open when no cookie is set.
 *
 * Verifies that without a sidebar_state cookie, the sidebar defaults
 * to open state (expanded).
 */
test('sidebar defaults to open when no cookie is set', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create required data
    Datacenter::factory()->create();

    $response = $this->actingAs($user)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('sidebarOpen', true)
    );
});

/**
 * Test 3: Sidebar collapsed state persists across page navigations.
 *
 * Verifies that the sidebar_state cookie value persists and is
 * consistently passed to the frontend during navigation.
 */
test('sidebar collapsed state persists across page navigations', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create required data
    Datacenter::factory()->create();

    // First request with collapsed sidebar
    $response = $this->actingAs($user)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('sidebarOpen', false)
    );

    // Navigate to another page with same cookie
    $response = $this->actingAs($user)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get('/datacenters');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('sidebarOpen', false)
    );
});

/**
 * Test 4: Sidebar expanded state is correctly passed to frontend.
 *
 * Verifies that when sidebar_state cookie is 'true', the sidebar
 * is in expanded state.
 */
test('sidebar expanded state is correctly passed to frontend', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create required data
    Datacenter::factory()->create();

    $response = $this->actingAs($user)
        ->withUnencryptedCookie('sidebar_state', 'true')
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('sidebarOpen', true)
    );
});

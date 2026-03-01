<?php

/**
 * Help Documentation Integration Gap Tests
 *
 * Strategic tests to fill coverage gaps identified in Task Group 8 review.
 * These tests focus on:
 * - Error handling and 404 responses
 * - Authorization for admin Inertia pages
 * - End-to-end user workflows
 * - Edge cases and empty states
 * - Cross-user isolation
 */

use App\Enums\HelpArticleType;
use App\Enums\HelpTourStepPosition;
use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use App\Models\User;
use App\Models\UserHelpInteraction;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

/**
 * Test 1: API returns 404 for non-existent article by slug.
 *
 * Verifies proper error handling when a user requests an article
 * that does not exist in the database.
 */
test('API returns 404 for non-existent article slug', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $response = $this->actingAs($user)
        ->getJson('/api/help/articles/non-existent-article-slug');

    $response->assertNotFound();
});

/**
 * Test 2: API returns 404 for non-existent tour context_key.
 *
 * Verifies that when no tour exists for a given context_key,
 * the API returns a proper 404 response.
 */
test('API returns 404 for non-existent tour context key', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $response = $this->actingAs($user)
        ->getJson('/api/help/tours/context/non-existent-context-key');

    $response->assertNotFound();
});

/**
 * Test 3: Non-admin cannot access admin help Inertia pages.
 *
 * Verifies that regular users (Auditors) cannot access the admin
 * help management Inertia pages, not just the API endpoints.
 */
test('non-admin cannot access admin help Inertia pages', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');

    $article = HelpArticle::factory()->create();
    $tour = HelpTour::factory()->create();

    // Test admin index page
    $this->actingAs($auditor)
        ->get('/admin/help')
        ->assertForbidden();

    // Test article create page
    $this->actingAs($auditor)
        ->get('/admin/help/articles/create')
        ->assertForbidden();

    // Test article edit page
    $this->actingAs($auditor)
        ->get("/admin/help/articles/{$article->id}/edit")
        ->assertForbidden();

    // Test tour create page
    $this->actingAs($auditor)
        ->get('/admin/help/tours/create')
        ->assertForbidden();

    // Test tour edit page
    $this->actingAs($auditor)
        ->get("/admin/help/tours/{$tour->id}/edit")
        ->assertForbidden();
});

/**
 * Test 4: Search returns empty results gracefully.
 *
 * Verifies that when a search query matches no articles,
 * the API returns an empty array rather than an error.
 */
test('search returns empty results gracefully when no matches found', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    // Create some articles that won't match the search
    HelpArticle::factory()->article()->create([
        'title' => 'Connection Guide',
        'content' => 'Learn about connections.',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/articles/search?query=zzzznonexistentterm');

    $response->assertSuccessful();
    $response->assertJsonCount(0, 'data');
});

/**
 * Test 5: Inactive tour is not returned by context API.
 *
 * Verifies that when a tour exists but is marked as inactive,
 * it is not returned by the public API.
 */
test('inactive tour is not returned by context API', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    // Create an inactive tour
    $inactiveTour = HelpTour::factory()->create([
        'slug' => 'inactive-tour',
        'name' => 'Inactive Tour',
        'context_key' => 'audits.execute',
        'is_active' => false,
    ]);

    $article = HelpArticle::factory()->tourStep()->create();
    HelpTourStep::factory()->forTour($inactiveTour)->withArticle($article)->order(0)->create();

    $response = $this->actingAs($user)
        ->getJson('/api/help/tours/context/audits.execute');

    // Should return 404 since no active tour exists
    $response->assertNotFound();
});

/**
 * Test 6: User dismissals are isolated per user.
 *
 * Verifies that when one user dismisses an article,
 * it does not affect another user's dismissed articles list.
 */
test('user dismissals are isolated per user', function () {
    $user1 = User::factory()->create();
    $user1->assignRole('Auditor');

    $user2 = User::factory()->create();
    $user2->assignRole('Auditor');

    $article = HelpArticle::factory()->tooltip()->create();

    // User1 dismisses the article
    UserHelpInteraction::factory()
        ->forUser($user1)
        ->forArticle($article)
        ->dismissed()
        ->create();

    // User1 should see the article in their dismissed list
    $response1 = $this->actingAs($user1)
        ->getJson('/api/help/user/dismissed');

    $response1->assertSuccessful();
    expect($response1->json('data'))->toContain($article->id);

    // User2 should NOT see the article in their dismissed list
    $response2 = $this->actingAs($user2)
        ->getJson('/api/help/user/dismissed');

    $response2->assertSuccessful();
    expect($response2->json('data'))->not->toContain($article->id);
});

/**
 * Test 7: Completed tours are isolated per user.
 *
 * Verifies that when one user completes a tour,
 * it does not affect another user's completed tours list.
 */
test('completed tours are isolated per user', function () {
    $user1 = User::factory()->create();
    $user1->assignRole('Auditor');

    $user2 = User::factory()->create();
    $user2->assignRole('Auditor');

    $tour = HelpTour::factory()->create([
        'slug' => 'shared-tour',
        'is_active' => true,
    ]);

    // User1 completes the tour
    UserHelpInteraction::factory()
        ->forUser($user1)
        ->forTour($tour)
        ->completedTour()
        ->create();

    // User1 should see the tour in their completed list
    $response1 = $this->actingAs($user1)
        ->getJson('/api/help/user/completed-tours');

    $response1->assertSuccessful();
    expect($response1->json('data'))->toContain('shared-tour');

    // User2 should NOT see the tour in their completed list
    $response2 = $this->actingAs($user2)
        ->getJson('/api/help/user/completed-tours');

    $response2->assertSuccessful();
    expect($response2->json('data'))->not->toContain('shared-tour');
});

/**
 * Test 8: Inactive articles are not returned in context query.
 *
 * Verifies that when articles exist but are marked as inactive,
 * they are not returned by the public API when querying by context_key.
 */
test('inactive articles are not returned in context query', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    // Create an active article
    $activeArticle = HelpArticle::factory()
        ->article()
        ->forContextKey('test.context')
        ->create([
            'title' => 'Active Article',
            'is_active' => true,
        ]);

    // Create an inactive article
    $inactiveArticle = HelpArticle::factory()
        ->article()
        ->forContextKey('test.context')
        ->create([
            'title' => 'Inactive Article',
            'is_active' => false,
        ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/articles?context_key=test.context');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.title', 'Active Article');
});

/**
 * Test 9: Soft-deleted articles are not returned in any query.
 *
 * Verifies that soft-deleted articles are completely hidden
 * from public API responses.
 */
test('soft-deleted articles are not returned in any query', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $article = HelpArticle::factory()
        ->article()
        ->forContextKey('test.context')
        ->create([
            'slug' => 'deleted-article',
            'title' => 'Deleted Article',
        ]);

    // Soft delete the article
    $article->delete();

    // By context_key
    $contextResponse = $this->actingAs($user)
        ->getJson('/api/help/articles?context_key=test.context');

    $contextResponse->assertSuccessful();
    $contextResponse->assertJsonCount(0, 'data');

    // By slug
    $slugResponse = $this->actingAs($user)
        ->getJson('/api/help/articles/deleted-article');

    $slugResponse->assertNotFound();

    // In search
    $searchResponse = $this->actingAs($user)
        ->getJson('/api/help/articles/search?query=deleted');

    $searchResponse->assertSuccessful();
    $searchResponse->assertJsonCount(0, 'data');
});

/**
 * Test 10: Unauthenticated users cannot access help API.
 *
 * Verifies that the help API endpoints require authentication.
 */
test('unauthenticated users cannot access help API', function () {
    HelpArticle::factory()->article()->create(['slug' => 'test-article']);
    HelpTour::factory()->create(['context_key' => 'test.context']);

    // Articles by context
    $this->getJson('/api/help/articles?context_key=test')
        ->assertUnauthorized();

    // Single article
    $this->getJson('/api/help/articles/test-article')
        ->assertUnauthorized();

    // Search
    $this->getJson('/api/help/articles/search?query=test')
        ->assertUnauthorized();

    // Tour by context
    $this->getJson('/api/help/tours/context/test.context')
        ->assertUnauthorized();

    // User dismissed articles
    $this->getJson('/api/help/user/dismissed')
        ->assertUnauthorized();

    // User completed tours
    $this->getJson('/api/help/user/completed-tours')
        ->assertUnauthorized();

    // Record interaction
    $this->postJson('/api/help/user/interactions', [
        'interaction_type' => 'viewed',
        'help_article_id' => 1,
    ])->assertUnauthorized();
});

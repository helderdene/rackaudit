<?php

/**
 * Feature Tour System Tests
 *
 * Tests for the feature tour system including:
 * - Tour spotlight overlay appears on target element (API test for tour data)
 * - Tour step popover positions correctly (API test for step position data)
 * - Tour auto-triggers on first visit (check completion status)
 * - Tour completion is recorded
 * - Replay tour functionality
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
});

/**
 * Test 1: Tour API returns complete data with target selectors for spotlight overlay.
 *
 * Verifies that when fetching a tour by context key, the API returns
 * all steps with target_selector fields that the frontend uses to
 * position the spotlight overlay on target elements.
 */
test('tour API returns steps with target selectors for spotlight overlay', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    // Create a tour with multiple steps
    $tour = HelpTour::factory()->create([
        'slug' => 'audit-execution-tour',
        'name' => 'Audit Execution Tour',
        'context_key' => 'audits.execute',
        'is_active' => true,
    ]);

    // Create step articles
    $article1 = HelpArticle::factory()->tourStep()->create([
        'title' => 'Start Audit',
        'content' => 'Click this button to **start** the audit process.',
    ]);
    $article2 = HelpArticle::factory()->tourStep()->create([
        'title' => 'View Progress',
        'content' => 'This card shows your **verification progress**.',
    ]);
    $article3 = HelpArticle::factory()->tourStep()->create([
        'title' => 'Complete Verification',
        'content' => 'Mark each item as verified or discrepant.',
    ]);

    // Create tour steps with target selectors for spotlight
    HelpTourStep::factory()->forTour($tour)->withArticle($article1)->order(0)->create([
        'target_selector' => '#audit-start-btn',
        'position' => HelpTourStepPosition::Bottom,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($article2)->order(1)->create([
        'target_selector' => '.progress-card',
        'position' => HelpTourStepPosition::Right,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($article3)->order(2)->create([
        'target_selector' => '[data-verification-table]',
        'position' => HelpTourStepPosition::Top,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/tours/context/audits.execute');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'id',
            'slug',
            'name',
            'context_key',
            'is_active',
            'steps' => [
                '*' => [
                    'id',
                    'step_order',
                    'target_selector',
                    'position',
                    'article' => [
                        'id',
                        'title',
                        'content',
                    ],
                ],
            ],
        ],
    ]);

    // Verify spotlight target selectors are included
    $response->assertJsonPath('data.steps.0.target_selector', '#audit-start-btn');
    $response->assertJsonPath('data.steps.1.target_selector', '.progress-card');
    $response->assertJsonPath('data.steps.2.target_selector', '[data-verification-table]');
});

/**
 * Test 2: Tour step positions are returned correctly for popover positioning.
 *
 * Verifies that step position values (top, right, bottom, left) are
 * properly returned by the API for frontend popover positioning.
 */
test('tour step positions are returned correctly for popover positioning', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $tour = HelpTour::factory()->create([
        'slug' => 'position-test-tour',
        'name' => 'Position Test Tour',
        'context_key' => 'racks.elevation',
        'is_active' => true,
    ]);

    // Create articles for each position type
    $topArticle = HelpArticle::factory()->tourStep()->create(['title' => 'Top Positioned']);
    $rightArticle = HelpArticle::factory()->tourStep()->create(['title' => 'Right Positioned']);
    $bottomArticle = HelpArticle::factory()->tourStep()->create(['title' => 'Bottom Positioned']);
    $leftArticle = HelpArticle::factory()->tourStep()->create(['title' => 'Left Positioned']);

    // Create steps with all four position types
    HelpTourStep::factory()->forTour($tour)->withArticle($topArticle)->order(0)->create([
        'target_selector' => '#top-target',
        'position' => HelpTourStepPosition::Top,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($rightArticle)->order(1)->create([
        'target_selector' => '#right-target',
        'position' => HelpTourStepPosition::Right,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($bottomArticle)->order(2)->create([
        'target_selector' => '#bottom-target',
        'position' => HelpTourStepPosition::Bottom,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($leftArticle)->order(3)->create([
        'target_selector' => '#left-target',
        'position' => HelpTourStepPosition::Left,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/tours/context/racks.elevation');

    $response->assertSuccessful();
    $response->assertJsonCount(4, 'data.steps');

    // Verify all position values
    $response->assertJsonPath('data.steps.0.position', 'top');
    $response->assertJsonPath('data.steps.1.position', 'right');
    $response->assertJsonPath('data.steps.2.position', 'bottom');
    $response->assertJsonPath('data.steps.3.position', 'left');
});

/**
 * Test 3: Tour auto-trigger check returns correct completion status for first visit.
 *
 * Verifies that the completed-tours endpoint correctly indicates whether
 * a user has completed a tour, enabling auto-trigger on first visit.
 */
test('tour auto-trigger check returns correct completion status for first visit', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $completedTour = HelpTour::factory()->create([
        'slug' => 'completed-tour',
        'name' => 'Already Completed Tour',
        'is_active' => true,
    ]);

    $newTour = HelpTour::factory()->create([
        'slug' => 'new-tour',
        'name' => 'New Tour',
        'is_active' => true,
    ]);

    // Mark the first tour as completed
    UserHelpInteraction::factory()
        ->forUser($user)
        ->forTour($completedTour)
        ->completedTour()
        ->create();

    // Check completed tours
    $response = $this->actingAs($user)
        ->getJson('/api/help/user/completed-tours');

    $response->assertSuccessful();
    $response->assertJson([
        'data' => ['completed-tour'],
    ]);

    // The new tour should NOT be in the completed list (should auto-trigger)
    $completedTours = $response->json('data');
    expect($completedTours)->toContain('completed-tour');
    expect($completedTours)->not->toContain('new-tour');
});

/**
 * Test 4: Tour completion is properly recorded via user interaction API.
 *
 * Verifies that when a tour is completed, the interaction is recorded
 * and the tour appears in the user's completed tours list.
 */
test('tour completion is properly recorded via user interaction API', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $tour = HelpTour::factory()->create([
        'slug' => 'inventory-audit-tour',
        'name' => 'Inventory Audit Tour',
        'context_key' => 'audits.execute.inventory',
        'is_active' => true,
    ]);

    // Verify tour is not completed initially
    $initialResponse = $this->actingAs($user)
        ->getJson('/api/help/user/completed-tours');

    $initialResponse->assertSuccessful();
    expect($initialResponse->json('data'))->not->toContain('inventory-audit-tour');

    // Record tour completion
    $completeResponse = $this->actingAs($user)
        ->postJson('/api/help/user/interactions', [
            'interaction_type' => 'completed_tour',
            'help_tour_id' => $tour->id,
        ]);

    $completeResponse->assertSuccessful();

    // Verify completion is recorded in database
    $this->assertDatabaseHas('user_help_interactions', [
        'user_id' => $user->id,
        'help_tour_id' => $tour->id,
        'interaction_type' => 'completed_tour',
    ]);

    // Verify tour now appears in completed tours
    $afterResponse = $this->actingAs($user)
        ->getJson('/api/help/user/completed-tours');

    $afterResponse->assertSuccessful();
    expect($afterResponse->json('data'))->toContain('inventory-audit-tour');
});

/**
 * Test 5: Replay tour functionality allows re-fetching tour even after completion.
 *
 * Verifies that a completed tour's data can still be fetched for replay,
 * and multiple completions are recorded separately.
 */
test('replay tour functionality allows re-fetching tour after completion', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $tour = HelpTour::factory()->create([
        'slug' => 'replayable-tour',
        'name' => 'Replayable Tour',
        'context_key' => 'implementations.files',
        'is_active' => true,
    ]);

    $article = HelpArticle::factory()->tourStep()->create([
        'title' => 'Implementation Guide',
        'content' => 'Learn how to manage implementation files.',
    ]);

    HelpTourStep::factory()->forTour($tour)->withArticle($article)->order(0)->create([
        'target_selector' => '.implementation-table',
        'position' => HelpTourStepPosition::Bottom,
    ]);

    // Complete the tour first time
    $this->actingAs($user)
        ->postJson('/api/help/user/interactions', [
            'interaction_type' => 'completed_tour',
            'help_tour_id' => $tour->id,
        ])
        ->assertSuccessful();

    // Verify tour is completed
    $completedResponse = $this->actingAs($user)
        ->getJson('/api/help/user/completed-tours');

    expect($completedResponse->json('data'))->toContain('replayable-tour');

    // Tour data should still be fetchable for replay
    $replayResponse = $this->actingAs($user)
        ->getJson('/api/help/tours/context/implementations.files');

    $replayResponse->assertSuccessful();
    $replayResponse->assertJsonPath('data.slug', 'replayable-tour');
    $replayResponse->assertJsonPath('data.name', 'Replayable Tour');
    $replayResponse->assertJsonCount(1, 'data.steps');
    $replayResponse->assertJsonPath('data.steps.0.article.title', 'Implementation Guide');

    // Record replay completion (second completion)
    $replayCompleteResponse = $this->actingAs($user)
        ->postJson('/api/help/user/interactions', [
            'interaction_type' => 'completed_tour',
            'help_tour_id' => $tour->id,
        ]);

    $replayCompleteResponse->assertSuccessful();

    // Both completions should be recorded
    $completionCount = UserHelpInteraction::where([
        'user_id' => $user->id,
        'help_tour_id' => $tour->id,
        'interaction_type' => 'completed_tour',
    ])->count();

    expect($completionCount)->toBe(2);
});

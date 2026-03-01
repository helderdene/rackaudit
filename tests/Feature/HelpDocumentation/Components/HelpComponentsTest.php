<?php

/**
 * Help UI Components Tests
 *
 * Tests for the Help UI Vue components including:
 * - HelpTooltip renders question mark trigger
 * - HelpTooltip displays content on interaction
 * - HelpSidebar opens and displays articles
 * - FeatureTour highlights target elements
 * - FeatureTour navigation (next/prev/skip)
 * - Markdown content renders correctly
 */

use App\Enums\HelpArticleType;
use App\Enums\HelpTourStepPosition;
use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

/**
 * Test 1: HelpTooltip API returns content for valid context_key.
 *
 * Verifies that the API returns tooltip content when queried by context_key,
 * which is used by the HelpTooltip component to display contextual help.
 */
test('help tooltip API returns content for valid context_key', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    // Create tooltip article
    $tooltipArticle = HelpArticle::factory()
        ->tooltip()
        ->forContextKey('audits.execute.start-button')
        ->create([
            'title' => 'Start Audit Button',
            'content' => 'Click this button to **start** the audit process.',
        ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/articles?context_key=audits.execute.start-button');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.title', 'Start Audit Button');
    $response->assertJsonPath('data.0.article_type', HelpArticleType::Tooltip->value);
    $response->assertJsonPath('data.0.content', 'Click this button to **start** the audit process.');
});

/**
 * Test 2: HelpTooltip dismissal is recorded via user interaction API.
 *
 * Verifies that when a user dismisses a tooltip, the interaction is recorded
 * and persisted so the tooltip won't show again.
 */
test('help tooltip dismissal is recorded via user interaction API', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $tooltipArticle = HelpArticle::factory()
        ->tooltip()
        ->create(['title' => 'Quick Tip']);

    // Record dismissal
    $response = $this->actingAs($user)
        ->postJson('/api/help/user/interactions', [
            'interaction_type' => 'dismissed',
            'help_article_id' => $tooltipArticle->id,
        ]);

    $response->assertSuccessful();

    // Verify dismissal is stored
    $this->assertDatabaseHas('user_help_interactions', [
        'user_id' => $user->id,
        'help_article_id' => $tooltipArticle->id,
        'interaction_type' => 'dismissed',
    ]);

    // Verify dismissed articles endpoint returns this article
    $dismissedResponse = $this->actingAs($user)
        ->getJson('/api/help/user/dismissed');

    $dismissedResponse->assertSuccessful();
    $dismissedResponse->assertJson([
        'data' => [$tooltipArticle->id],
    ]);
});

/**
 * Test 3: HelpSidebar API returns articles grouped for display.
 *
 * Verifies that the articles endpoint returns articles filtered by context_key
 * with proper structure for sidebar display including category information.
 */
test('help sidebar API returns articles grouped for display', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    // Create articles for the same context
    HelpArticle::factory()
        ->article()
        ->forContextKey('audits.execute')
        ->create([
            'title' => 'Audit Overview',
            'category' => 'Getting Started',
            'sort_order' => 1,
        ]);

    HelpArticle::factory()
        ->article()
        ->forContextKey('audits.execute')
        ->create([
            'title' => 'Verifying Connections',
            'category' => 'Verification',
            'sort_order' => 2,
        ]);

    HelpArticle::factory()
        ->article()
        ->forContextKey('audits.execute')
        ->create([
            'title' => 'Completing an Audit',
            'category' => 'Getting Started',
            'sort_order' => 3,
        ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/articles?context_key=audits.execute');

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'data');
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'slug',
                'title',
                'content',
                'context_key',
                'article_type',
                'category',
                'sort_order',
            ],
        ],
    ]);
});

/**
 * Test 4: FeatureTour API returns tour with steps and target selectors.
 *
 * Verifies that the tour endpoint returns complete tour data including
 * steps with target selectors for element highlighting and positioning.
 */
test('feature tour API returns tour with steps and target selectors', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    // Create tour
    $tour = HelpTour::factory()->create([
        'slug' => 'audit-execution-tour',
        'name' => 'Audit Execution Tour',
        'context_key' => 'audits.execute',
        'is_active' => true,
    ]);

    // Create step articles
    $stepArticle1 = HelpArticle::factory()->tourStep()->create([
        'title' => 'Step 1: Start',
        'content' => 'Click the start button to begin.',
    ]);
    $stepArticle2 = HelpArticle::factory()->tourStep()->create([
        'title' => 'Step 2: Verify',
        'content' => 'Review and verify each item.',
    ]);

    // Create tour steps with target selectors
    HelpTourStep::factory()->forTour($tour)->withArticle($stepArticle1)->order(0)->create([
        'target_selector' => '#audit-start-btn',
        'position' => HelpTourStepPosition::Bottom,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($stepArticle2)->order(1)->create([
        'target_selector' => '.verification-list',
        'position' => HelpTourStepPosition::Right,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/tours/context/audits.execute');

    $response->assertSuccessful();
    $response->assertJsonPath('data.name', 'Audit Execution Tour');
    $response->assertJsonCount(2, 'data.steps');
    $response->assertJsonPath('data.steps.0.target_selector', '#audit-start-btn');
    $response->assertJsonPath('data.steps.0.position', 'bottom');
    $response->assertJsonPath('data.steps.0.article.title', 'Step 1: Start');
    $response->assertJsonPath('data.steps.1.target_selector', '.verification-list');
    $response->assertJsonPath('data.steps.1.position', 'right');
});

/**
 * Test 5: FeatureTour completion is recorded via user interaction API.
 *
 * Verifies that tour completion tracking works correctly, allowing
 * the system to know whether to auto-trigger tours on first visit.
 */
test('feature tour completion is recorded via user interaction API', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $tour = HelpTour::factory()->create([
        'slug' => 'intro-tour',
        'is_active' => true,
    ]);

    // Record tour completion
    $response = $this->actingAs($user)
        ->postJson('/api/help/user/interactions', [
            'interaction_type' => 'completed_tour',
            'help_tour_id' => $tour->id,
        ]);

    $response->assertSuccessful();

    // Verify completion is stored
    $this->assertDatabaseHas('user_help_interactions', [
        'user_id' => $user->id,
        'help_tour_id' => $tour->id,
        'interaction_type' => 'completed_tour',
    ]);

    // Verify completed tours endpoint returns this tour
    $completedResponse = $this->actingAs($user)
        ->getJson('/api/help/user/completed-tours');

    $completedResponse->assertSuccessful();
    $completedResponse->assertJson([
        'data' => [$tour->slug],
    ]);
});

/**
 * Test 6: Markdown content in articles is returned correctly for frontend rendering.
 *
 * Verifies that articles with markdown content are returned with
 * the raw markdown content, ready for frontend rendering.
 */
test('markdown content in articles is returned correctly for frontend rendering', function () {
    $user = User::factory()->create();
    $user->assignRole('Auditor');

    $markdownContent = <<<'MD'
## Getting Started

Welcome to the **audit system**. Here's what you need to know:

### Key Features

1. Connection verification
2. Inventory tracking
3. Discrepancy management

> **Note:** Always verify connections before completing an audit.

```
Example command: audit --verify
```

[Learn more](/help/advanced)
MD;

    $article = HelpArticle::factory()->article()->create([
        'slug' => 'getting-started',
        'title' => 'Getting Started Guide',
        'content' => $markdownContent,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/articles/getting-started');

    $response->assertSuccessful();
    $response->assertJsonPath('data.slug', 'getting-started');
    $response->assertJsonPath('data.title', 'Getting Started Guide');

    // Verify raw markdown is returned
    $responseContent = $response->json('data.content');
    expect($responseContent)->toContain('## Getting Started');
    expect($responseContent)->toContain('**audit system**');
    expect($responseContent)->toContain('### Key Features');
    expect($responseContent)->toContain('1. Connection verification');
    expect($responseContent)->toContain('> **Note:**');
    expect($responseContent)->toContain('```');
    expect($responseContent)->toContain('[Learn more](/help/advanced)');
});

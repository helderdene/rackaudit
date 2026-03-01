<?php

use App\Enums\HelpArticleType;
use App\Enums\HelpInteractionType;
use App\Enums\HelpTourStepPosition;
use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use App\Models\User;
use App\Models\UserHelpInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('fetching articles by context_key returns matching active articles', function () {
    $user = User::factory()->create();

    // Create articles with matching context_key
    $matchingArticle1 = HelpArticle::factory()
        ->forContextKey('audits.execute')
        ->article()
        ->create(['title' => 'How to Execute Audits']);
    $matchingArticle2 = HelpArticle::factory()
        ->forContextKey('audits.execute')
        ->tooltip()
        ->create(['title' => 'Audit Execution Tips']);

    // Create article with different context_key (should not be returned)
    HelpArticle::factory()
        ->forContextKey('connections.create')
        ->create();

    // Create inactive article (should not be returned)
    HelpArticle::factory()
        ->forContextKey('audits.execute')
        ->inactive()
        ->create();

    $response = $this->actingAs($user)
        ->getJson('/api/help/articles?context_key=audits.execute');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['title' => 'How to Execute Audits']);
    $response->assertJsonFragment(['title' => 'Audit Execution Tips']);
});

test('fetching single article by slug returns the article with content', function () {
    $user = User::factory()->create();

    $article = HelpArticle::factory()->create([
        'slug' => 'getting-started-guide',
        'title' => 'Getting Started Guide',
        'content' => '## Welcome\n\nThis is the getting started guide.',
        'category' => 'Getting Started',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/articles/getting-started-guide');

    $response->assertSuccessful();
    $response->assertJsonPath('data.slug', 'getting-started-guide');
    $response->assertJsonPath('data.title', 'Getting Started Guide');
    $response->assertJsonPath('data.category', 'Getting Started');
    $response->assertJsonStructure([
        'data' => [
            'id',
            'slug',
            'title',
            'content',
            'context_key',
            'article_type',
            'category',
        ],
    ]);
});

test('fetching tour with steps by context_key returns tour with nested steps and articles', function () {
    $user = User::factory()->create();

    $tour = HelpTour::factory()->create([
        'slug' => 'audit-execution-tour',
        'name' => 'Audit Execution Tour',
        'context_key' => 'audits.execute',
        'is_active' => true,
    ]);

    $article1 = HelpArticle::factory()->tourStep()->create(['title' => 'Step 1: Start Audit']);
    $article2 = HelpArticle::factory()->tourStep()->create(['title' => 'Step 2: Verify Items']);
    $article3 = HelpArticle::factory()->tourStep()->create(['title' => 'Step 3: Complete Audit']);

    HelpTourStep::factory()->forTour($tour)->withArticle($article1)->order(0)->create([
        'target_selector' => '#start-button',
        'position' => HelpTourStepPosition::Bottom,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($article2)->order(1)->create([
        'target_selector' => '#verify-section',
        'position' => HelpTourStepPosition::Right,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($article3)->order(2)->create([
        'target_selector' => '#complete-button',
        'position' => HelpTourStepPosition::Top,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/tours/context/audits.execute');

    $response->assertSuccessful();
    $response->assertJsonPath('data.slug', 'audit-execution-tour');
    $response->assertJsonPath('data.name', 'Audit Execution Tour');
    $response->assertJsonCount(3, 'data.steps');
    $response->assertJsonPath('data.steps.0.article.title', 'Step 1: Start Audit');
    $response->assertJsonPath('data.steps.0.target_selector', '#start-button');
    $response->assertJsonPath('data.steps.0.position', 'bottom');
});

test('search endpoint returns matching articles with highlighted snippets', function () {
    $user = User::factory()->create();

    HelpArticle::factory()->article()->create([
        'title' => 'Connection Auditing Guide',
        'content' => 'Learn how to perform connection audits effectively.',
    ]);
    HelpArticle::factory()->article()->create([
        'title' => 'Inventory Management',
        'content' => 'This guide covers inventory tracking and auditing procedures.',
    ]);
    HelpArticle::factory()->article()->create([
        'title' => 'Getting Started',
        'content' => 'Welcome to the application.',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/help/articles/search?query=audit');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');

    // Verify the response includes search-related fields
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'slug',
                'title',
                'excerpt',
            ],
        ],
    ]);
});

test('dismissed articles endpoint returns current user dismissed article ids', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $article1 = HelpArticle::factory()->create();
    $article2 = HelpArticle::factory()->create();
    $article3 = HelpArticle::factory()->create();

    // User has dismissed article1 and article2
    UserHelpInteraction::factory()
        ->forUser($user)
        ->forArticle($article1)
        ->dismissed()
        ->create();
    UserHelpInteraction::factory()
        ->forUser($user)
        ->forArticle($article2)
        ->dismissed()
        ->create();

    // User viewed article3 (not dismissed)
    UserHelpInteraction::factory()
        ->forUser($user)
        ->forArticle($article3)
        ->viewed()
        ->create();

    // Other user dismissed article3 (should not affect our user)
    UserHelpInteraction::factory()
        ->forUser($otherUser)
        ->forArticle($article3)
        ->dismissed()
        ->create();

    $response = $this->actingAs($user)
        ->getJson('/api/help/user/dismissed');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
    $response->assertJson([
        'data' => [$article1->id, $article2->id],
    ]);
});

test('recording user interactions stores view and dismiss correctly', function () {
    $user = User::factory()->create();
    $article = HelpArticle::factory()->create();
    $tour = HelpTour::factory()->create();

    // Record a view interaction
    $viewResponse = $this->actingAs($user)
        ->postJson('/api/help/user/interactions', [
            'interaction_type' => 'viewed',
            'help_article_id' => $article->id,
        ]);

    $viewResponse->assertSuccessful();
    $this->assertDatabaseHas('user_help_interactions', [
        'user_id' => $user->id,
        'help_article_id' => $article->id,
        'interaction_type' => 'viewed',
    ]);

    // Record a dismiss interaction
    $dismissResponse = $this->actingAs($user)
        ->postJson('/api/help/user/interactions', [
            'interaction_type' => 'dismissed',
            'help_article_id' => $article->id,
        ]);

    $dismissResponse->assertSuccessful();
    $this->assertDatabaseHas('user_help_interactions', [
        'user_id' => $user->id,
        'help_article_id' => $article->id,
        'interaction_type' => 'dismissed',
    ]);

    // Record a completed tour interaction
    $tourResponse = $this->actingAs($user)
        ->postJson('/api/help/user/interactions', [
            'interaction_type' => 'completed_tour',
            'help_tour_id' => $tour->id,
        ]);

    $tourResponse->assertSuccessful();
    $this->assertDatabaseHas('user_help_interactions', [
        'user_id' => $user->id,
        'help_tour_id' => $tour->id,
        'interaction_type' => 'completed_tour',
    ]);
});

<?php

use App\Models\HelpArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Help Center page renders article list', function () {
    $user = User::factory()->create();

    // Create articles in different categories
    $article1 = HelpArticle::factory()->article()->create([
        'title' => 'Getting Started Guide',
        'category' => 'Getting Started',
    ]);
    $article2 = HelpArticle::factory()->article()->create([
        'title' => 'Audit Execution Overview',
        'category' => 'Audits',
    ]);
    $article3 = HelpArticle::factory()->article()->create([
        'title' => 'Connection Management',
        'category' => 'Connections',
    ]);

    // Create an inactive article (should not appear)
    HelpArticle::factory()->article()->inactive()->create([
        'title' => 'Inactive Article',
        'category' => 'Getting Started',
    ]);

    $response = $this->actingAs($user)
        ->get('/help');

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Index')
            ->has('articles', 3)
            ->has('categories')
            ->where('categories', fn ($categories) => count($categories) === 3
                && in_array('Getting Started', $categories->toArray())
                && in_array('Audits', $categories->toArray())
                && in_array('Connections', $categories->toArray())
            )
    );
});

test('category filtering works', function () {
    $user = User::factory()->create();

    HelpArticle::factory()->article()->create([
        'title' => 'Audit Guide',
        'category' => 'Audits',
    ]);
    HelpArticle::factory()->article()->create([
        'title' => 'Connection Guide',
        'category' => 'Connections',
    ]);

    $response = $this->actingAs($user)
        ->get('/help?category=Audits');

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Index')
            ->has('articles', 1)
            ->where('articles.0.category', 'Audits')
    );
});

test('search filters articles by title and content', function () {
    $user = User::factory()->create();

    HelpArticle::factory()->article()->create([
        'title' => 'Connection Audit Guide',
        'content' => 'Learn how to perform connection audits.',
    ]);
    HelpArticle::factory()->article()->create([
        'title' => 'Inventory Management',
        'content' => 'Track your inventory effectively.',
    ]);
    HelpArticle::factory()->article()->create([
        'title' => 'Device Tracking',
        'content' => 'This includes auditing device records.',
    ]);

    $response = $this->actingAs($user)
        ->get('/help?search=audit');

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Index')
            ->has('articles', 2)
    );
});

test('clicking article navigates to detail view', function () {
    $user = User::factory()->create();

    $article = HelpArticle::factory()->article()->create([
        'slug' => 'getting-started',
        'title' => 'Getting Started Guide',
        'content' => '## Welcome\n\nThis is the getting started guide with detailed instructions.',
        'category' => 'Getting Started',
    ]);

    $response = $this->actingAs($user)
        ->get('/help/getting-started');

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Show')
            ->has('article', fn ($article) => $article
                ->where('slug', 'getting-started')
                ->where('title', 'Getting Started Guide')
                ->has('content')
                ->etc()
            )
            ->has('relatedArticles')
    );
});

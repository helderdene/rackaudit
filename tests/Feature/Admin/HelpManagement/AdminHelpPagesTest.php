<?php

use App\Enums\HelpArticleType;
use App\Enums\HelpTourStepPosition;
use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create non-admin user
    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('Auditor');

    // Skip Vite manifest check in tests
    $this->withoutVite();
});

test('admin can view help articles list page', function () {
    // Create some test articles
    $articles = HelpArticle::factory()->count(5)->create();

    $response = $this->actingAs($this->admin)
        ->get('/admin/help');

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Help/Index')
            ->has('articles.data', 5)
            ->has('tours')
        );
});

test('admin can create new article with markdown editor', function () {
    $response = $this->actingAs($this->admin)
        ->get('/admin/help/articles/create');

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Help/ArticleForm')
            ->where('mode', 'create')
            ->has('articleTypes')
            ->has('categories')
            ->has('contextKeys')
        );

    // Now test form submission
    $articleData = [
        'slug' => 'new-test-article',
        'title' => 'New Test Article',
        'content' => '## Test Content\n\nThis is markdown content.',
        'context_key' => 'audits.execute',
        'article_type' => HelpArticleType::Article->value,
        'category' => 'Getting Started',
        'sort_order' => 10,
        'is_active' => true,
    ];

    $createResponse = $this->actingAs($this->admin)
        ->post('/admin/help/articles', $articleData);

    $createResponse->assertRedirect('/admin/help');

    $this->assertDatabaseHas('help_articles', [
        'slug' => 'new-test-article',
        'title' => 'New Test Article',
    ]);
});

test('admin can edit existing article', function () {
    $article = HelpArticle::factory()->create([
        'slug' => 'existing-article',
        'title' => 'Existing Article',
        'content' => 'Original content',
    ]);

    // Check edit page loads
    $response = $this->actingAs($this->admin)
        ->get("/admin/help/articles/{$article->id}/edit");

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Help/ArticleForm')
            ->where('mode', 'edit')
            ->where('article.slug', 'existing-article')
            ->where('article.title', 'Existing Article')
        );

    // Test update
    $updateResponse = $this->actingAs($this->admin)
        ->put("/admin/help/articles/{$article->id}", [
            'title' => 'Updated Article Title',
            'content' => 'Updated content',
        ]);

    $updateResponse->assertRedirect('/admin/help');

    $this->assertDatabaseHas('help_articles', [
        'id' => $article->id,
        'title' => 'Updated Article Title',
        'content' => 'Updated content',
    ]);
});

test('admin can manage tour steps add reorder remove', function () {
    $tour = HelpTour::factory()->create([
        'slug' => 'test-tour',
        'name' => 'Test Tour',
    ]);

    $article1 = HelpArticle::factory()->tourStep()->create();
    $article2 = HelpArticle::factory()->tourStep()->create();
    $article3 = HelpArticle::factory()->tourStep()->create();

    // Check edit page for tour
    $response = $this->actingAs($this->admin)
        ->get("/admin/help/tours/{$tour->id}/edit");

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Help/TourForm')
            ->where('mode', 'edit')
            ->where('tour.slug', 'test-tour')
            ->has('availableArticles')
        );

    // Update tour with steps
    $updateResponse = $this->actingAs($this->admin)
        ->put("/admin/help/tours/{$tour->id}", [
            'name' => 'Updated Tour',
            'description' => 'Updated description',
            'steps' => [
                [
                    'help_article_id' => $article1->id,
                    'target_selector' => '#step-1',
                    'position' => HelpTourStepPosition::Bottom->value,
                    'step_order' => 0,
                ],
                [
                    'help_article_id' => $article2->id,
                    'target_selector' => '#step-2',
                    'position' => HelpTourStepPosition::Right->value,
                    'step_order' => 1,
                ],
                [
                    'help_article_id' => $article3->id,
                    'target_selector' => '#step-3',
                    'position' => HelpTourStepPosition::Left->value,
                    'step_order' => 2,
                ],
            ],
        ]);

    // Tours redirect back with tab=tours
    $updateResponse->assertRedirect();
    expect($updateResponse->headers->get('Location'))->toContain('/admin/help');

    // Verify steps were created
    $tour->refresh();
    expect($tour->steps)->toHaveCount(3);

    // Test reordering - update with different order
    $reorderResponse = $this->actingAs($this->admin)
        ->put("/admin/help/tours/{$tour->id}", [
            'name' => 'Updated Tour',
            'steps' => [
                [
                    'help_article_id' => $article2->id,
                    'target_selector' => '#step-2',
                    'position' => HelpTourStepPosition::Right->value,
                    'step_order' => 0,
                ],
                [
                    'help_article_id' => $article1->id,
                    'target_selector' => '#step-1',
                    'position' => HelpTourStepPosition::Bottom->value,
                    'step_order' => 1,
                ],
            ],
        ]);

    $reorderResponse->assertRedirect();

    // Verify reordering and removal (article3 was removed)
    $tour->refresh();
    expect($tour->steps)->toHaveCount(2);
    expect($tour->steps->first()->help_article_id)->toBe($article2->id);
    expect($tour->steps->last()->help_article_id)->toBe($article1->id);
});

test('preview mode displays tooltip and tour correctly', function () {
    $article = HelpArticle::factory()->tooltip()->create([
        'slug' => 'preview-tooltip',
        'title' => 'Preview Tooltip',
        'content' => 'This is preview content',
        'context_key' => 'test.preview',
    ]);

    $tour = HelpTour::factory()->create([
        'slug' => 'preview-tour',
        'name' => 'Preview Tour',
    ]);

    $tourArticle = HelpArticle::factory()->tourStep()->create();
    HelpTourStep::factory()->create([
        'help_tour_id' => $tour->id,
        'help_article_id' => $tourArticle->id,
        'target_selector' => '#preview-element',
        'position' => HelpTourStepPosition::Bottom,
        'step_order' => 0,
    ]);

    // Test article preview endpoint
    $articlePreviewResponse = $this->actingAs($this->admin)
        ->get("/admin/help/articles/{$article->id}/preview");

    $articlePreviewResponse->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Help/ArticlePreview')
            ->has('article')
            ->where('article.slug', 'preview-tooltip')
        );

    // Test tour preview endpoint
    $tourPreviewResponse = $this->actingAs($this->admin)
        ->get("/admin/help/tours/{$tour->id}/preview");

    $tourPreviewResponse->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Help/TourPreview')
            ->has('tour')
            ->has('tour.steps')
            ->where('tour.slug', 'preview-tour')
        );
});

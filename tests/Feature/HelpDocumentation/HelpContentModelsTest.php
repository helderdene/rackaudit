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

test('help article can be created with required fields', function () {
    $article = HelpArticle::factory()->create([
        'slug' => 'test-article',
        'title' => 'Test Article Title',
        'content' => '## Test Content\n\nThis is a test.',
        'context_key' => 'audits.execute',
        'article_type' => HelpArticleType::Article,
        'category' => 'Getting Started',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    expect($article->slug)->toBe('test-article');
    expect($article->title)->toBe('Test Article Title');
    expect($article->content)->toContain('Test Content');
    expect($article->context_key)->toBe('audits.execute');
    expect($article->article_type)->toBe(HelpArticleType::Article);
    expect($article->category)->toBe('Getting Started');
    expect($article->sort_order)->toBe(10);
    expect($article->is_active)->toBeTrue();
});

test('help tour and help tour step relationship works correctly', function () {
    $tour = HelpTour::factory()->create([
        'slug' => 'test-tour',
        'name' => 'Test Tour',
        'context_key' => 'audits.execute',
        'is_active' => true,
    ]);

    $article1 = HelpArticle::factory()->tourStep()->create();
    $article2 = HelpArticle::factory()->tourStep()->create();
    $article3 = HelpArticle::factory()->tourStep()->create();

    HelpTourStep::factory()->forTour($tour)->withArticle($article1)->order(0)->create([
        'target_selector' => '#step-1',
        'position' => HelpTourStepPosition::Bottom,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($article2)->order(1)->create([
        'target_selector' => '#step-2',
        'position' => HelpTourStepPosition::Right,
    ]);
    HelpTourStep::factory()->forTour($tour)->withArticle($article3)->order(2)->create([
        'target_selector' => '#step-3',
        'position' => HelpTourStepPosition::Top,
    ]);

    $tour->refresh();

    expect($tour->steps)->toHaveCount(3);
    expect($tour->steps->first()->step_order)->toBe(0);
    expect($tour->steps->first()->article->id)->toBe($article1->id);
    expect($tour->steps->first()->position)->toBe(HelpTourStepPosition::Bottom);
    expect($tour->steps->last()->step_order)->toBe(2);
});

test('user help interaction tracking records viewed, dismissed, and completed tour', function () {
    $user = User::factory()->create();
    $article = HelpArticle::factory()->create();
    $tour = HelpTour::factory()->create();

    // Create viewed interaction
    $viewedInteraction = UserHelpInteraction::factory()
        ->forUser($user)
        ->forArticle($article)
        ->viewed()
        ->create();

    // Create dismissed interaction
    $dismissedInteraction = UserHelpInteraction::factory()
        ->forUser($user)
        ->forArticle($article)
        ->dismissed()
        ->create();

    // Create completed tour interaction
    $completedTourInteraction = UserHelpInteraction::factory()
        ->forUser($user)
        ->forTour($tour)
        ->completedTour()
        ->create();

    expect($viewedInteraction->interaction_type)->toBe(HelpInteractionType::Viewed);
    expect($viewedInteraction->user->id)->toBe($user->id);
    expect($viewedInteraction->helpArticle->id)->toBe($article->id);

    expect($dismissedInteraction->interaction_type)->toBe(HelpInteractionType::Dismissed);

    expect($completedTourInteraction->interaction_type)->toBe(HelpInteractionType::CompletedTour);
    expect($completedTourInteraction->helpTour->id)->toBe($tour->id);
    expect($completedTourInteraction->help_article_id)->toBeNull();
});

test('soft delete functionality works for help articles and tours', function () {
    $article = HelpArticle::factory()->create();
    $tour = HelpTour::factory()->create();

    $articleId = $article->id;
    $tourId = $tour->id;

    // Soft delete article
    $article->delete();

    // Article should not be found in normal queries
    expect(HelpArticle::find($articleId))->toBeNull();
    // But should be found with trashed
    expect(HelpArticle::withTrashed()->find($articleId))->not->toBeNull();
    expect(HelpArticle::withTrashed()->find($articleId)->deleted_at)->not->toBeNull();

    // Soft delete tour
    $tour->delete();

    // Tour should not be found in normal queries
    expect(HelpTour::find($tourId))->toBeNull();
    // But should be found with trashed
    expect(HelpTour::withTrashed()->find($tourId))->not->toBeNull();
    expect(HelpTour::withTrashed()->find($tourId)->deleted_at)->not->toBeNull();

    // Restore article
    HelpArticle::withTrashed()->find($articleId)->restore();
    expect(HelpArticle::find($articleId))->not->toBeNull();
});

test('context key scoping filters articles and tours correctly', function () {
    // Create articles with different context keys
    $auditArticle1 = HelpArticle::factory()->forContextKey('audits.execute')->create();
    $auditArticle2 = HelpArticle::factory()->forContextKey('audits.execute')->create();
    $connectionArticle = HelpArticle::factory()->forContextKey('connections.create')->create();
    $noContextArticle = HelpArticle::factory()->create(['context_key' => null]);

    // Create tours with different context keys
    $auditTour = HelpTour::factory()->forContextKey('audits.execute')->create();
    $rackTour = HelpTour::factory()->forContextKey('racks.elevation')->create();

    // Test article scoping
    $auditArticles = HelpArticle::query()->byContextKey('audits.execute')->get();
    expect($auditArticles)->toHaveCount(2);
    expect($auditArticles->pluck('id'))->toContain($auditArticle1->id, $auditArticle2->id);

    $connectionArticles = HelpArticle::query()->byContextKey('connections.create')->get();
    expect($connectionArticles)->toHaveCount(1);
    expect($connectionArticles->first()->id)->toBe($connectionArticle->id);

    // Test tour scoping
    $auditTours = HelpTour::query()->byContextKey('audits.execute')->get();
    expect($auditTours)->toHaveCount(1);
    expect($auditTours->first()->id)->toBe($auditTour->id);

    $rackTours = HelpTour::query()->byContextKey('racks.elevation')->get();
    expect($rackTours)->toHaveCount(1);
    expect($rackTours->first()->id)->toBe($rackTour->id);
});

test('article type enum validation casts correctly', function () {
    // Test tooltip type
    $tooltipArticle = HelpArticle::factory()->tooltip()->create();
    expect($tooltipArticle->article_type)->toBe(HelpArticleType::Tooltip);
    expect($tooltipArticle->article_type->value)->toBe('tooltip');
    expect($tooltipArticle->article_type->label())->toBe('Tooltip');

    // Test tour step type
    $tourStepArticle = HelpArticle::factory()->tourStep()->create();
    expect($tourStepArticle->article_type)->toBe(HelpArticleType::TourStep);
    expect($tourStepArticle->article_type->value)->toBe('tour_step');
    expect($tourStepArticle->article_type->label())->toBe('Tour Step');

    // Test article type
    $fullArticle = HelpArticle::factory()->article()->create();
    expect($fullArticle->article_type)->toBe(HelpArticleType::Article);
    expect($fullArticle->article_type->value)->toBe('article');
    expect($fullArticle->article_type->label())->toBe('Article');

    // Test byType scope
    $tooltips = HelpArticle::query()->byType(HelpArticleType::Tooltip)->get();
    expect($tooltips->pluck('id'))->toContain($tooltipArticle->id);
    expect($tooltips->pluck('id'))->not->toContain($fullArticle->id);
});

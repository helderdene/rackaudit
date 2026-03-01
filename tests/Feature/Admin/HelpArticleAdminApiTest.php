<?php

/**
 * Admin Help Article API Tests
 *
 * Tests for admin help article management API endpoints.
 * These tests validate the Inertia-based admin routes for help content.
 */

use App\Enums\HelpArticleType;
use App\Enums\HelpTourStepPosition;
use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create non-admin user
    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('Auditor');
});

test('admin can create new help article', function () {
    $articleData = [
        'slug' => 'getting-started-guide',
        'title' => 'Getting Started with Audits',
        'content' => '## Introduction\n\nThis guide helps you get started with audits.',
        'context_key' => 'audits.execute',
        'article_type' => HelpArticleType::Article->value,
        'category' => 'Getting Started',
        'sort_order' => 10,
        'is_active' => true,
    ];

    $response = $this->actingAs($this->admin)
        ->post('/admin/help/articles', $articleData);

    $response->assertRedirect('/admin/help');

    $this->assertDatabaseHas('help_articles', [
        'slug' => 'getting-started-guide',
        'title' => 'Getting Started with Audits',
        'context_key' => 'audits.execute',
        'article_type' => HelpArticleType::Article->value,
        'category' => 'Getting Started',
        'is_active' => true,
    ]);
});

test('admin can update existing article', function () {
    $article = HelpArticle::factory()->create([
        'slug' => 'original-slug',
        'title' => 'Original Title',
        'is_active' => true,
    ]);

    $updateData = [
        'title' => 'Updated Title',
        'content' => '## Updated Content\n\nThis content has been updated.',
        'is_active' => false,
    ];

    $response = $this->actingAs($this->admin)
        ->put("/admin/help/articles/{$article->id}", $updateData);

    $response->assertRedirect('/admin/help');

    $this->assertDatabaseHas('help_articles', [
        'id' => $article->id,
        'title' => 'Updated Title',
        'is_active' => false,
    ]);
});

test('admin can delete soft delete article', function () {
    $article = HelpArticle::factory()->create([
        'slug' => 'article-to-delete',
        'title' => 'Article to Delete',
    ]);

    $response = $this->actingAs($this->admin)
        ->delete("/admin/help/articles/{$article->id}");

    $response->assertRedirect('/admin/help');

    // Article should be soft deleted
    $this->assertSoftDeleted('help_articles', [
        'id' => $article->id,
    ]);

    // But should still exist in database with trashed
    expect(HelpArticle::withTrashed()->find($article->id))->not->toBeNull();
});

test('admin can manage tour steps', function () {
    $tour = HelpTour::factory()->create([
        'slug' => 'test-tour',
        'name' => 'Test Tour',
    ]);

    $article1 = HelpArticle::factory()->tourStep()->create();
    $article2 = HelpArticle::factory()->tourStep()->create();

    // Update tour with steps
    $tourData = [
        'name' => 'Updated Test Tour',
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
        ],
    ];

    $response = $this->actingAs($this->admin)
        ->put("/admin/help/tours/{$tour->id}", $tourData);

    $response->assertRedirect();

    // Verify tour was updated
    $tour->refresh();
    expect($tour->name)->toBe('Updated Test Tour');
    expect($tour->description)->toBe('Updated description');

    // Verify steps were created
    expect($tour->steps)->toHaveCount(2);
    expect($tour->steps->first()->target_selector)->toBe('#step-1');
    expect($tour->steps->first()->position)->toBe(HelpTourStepPosition::Bottom);
    expect($tour->steps->last()->target_selector)->toBe('#step-2');
});

test('non-admin cannot access admin endpoints', function () {
    $article = HelpArticle::factory()->create();
    $tour = HelpTour::factory()->create();

    // Test admin index page
    $this->actingAs($this->auditor)
        ->get('/admin/help')
        ->assertForbidden();

    // Test store
    $this->actingAs($this->auditor)
        ->post('/admin/help/articles', [
            'slug' => 'test-slug',
            'title' => 'Test Title',
            'content' => 'Test content',
            'article_type' => HelpArticleType::Article->value,
        ])
        ->assertForbidden();

    // Test update
    $this->actingAs($this->auditor)
        ->put("/admin/help/articles/{$article->id}", [
            'title' => 'Updated Title',
        ])
        ->assertForbidden();

    // Test delete
    $this->actingAs($this->auditor)
        ->delete("/admin/help/articles/{$article->id}")
        ->assertForbidden();

    // Test tour endpoints
    $this->actingAs($this->auditor)
        ->get('/admin/help/tours/create')
        ->assertForbidden();

    $this->actingAs($this->auditor)
        ->post('/admin/help/tours', [
            'slug' => 'test-tour',
            'name' => 'Test Tour',
        ])
        ->assertForbidden();
});

test('article validation rules are enforced', function () {
    // Test missing required fields
    $response = $this->actingAs($this->admin)
        ->post('/admin/help/articles', []);

    $response->assertSessionHasErrors(['slug', 'title', 'content', 'article_type']);

    // Test invalid article type
    $response = $this->actingAs($this->admin)
        ->post('/admin/help/articles', [
            'slug' => 'test-slug',
            'title' => 'Test Title',
            'content' => 'Test content',
            'article_type' => 'invalid_type',
        ]);

    $response->assertSessionHasErrors(['article_type']);

    // Test duplicate slug
    HelpArticle::factory()->create(['slug' => 'existing-slug']);

    $response = $this->actingAs($this->admin)
        ->post('/admin/help/articles', [
            'slug' => 'existing-slug',
            'title' => 'Test Title',
            'content' => 'Test content',
            'article_type' => HelpArticleType::Article->value,
        ]);

    $response->assertSessionHasErrors(['slug']);

    // Test slug format (must be lowercase with hyphens)
    $response = $this->actingAs($this->admin)
        ->post('/admin/help/articles', [
            'slug' => 'Invalid Slug With Spaces',
            'title' => 'Test Title',
            'content' => 'Test content',
            'article_type' => HelpArticleType::Article->value,
        ]);

    $response->assertSessionHasErrors(['slug']);
});

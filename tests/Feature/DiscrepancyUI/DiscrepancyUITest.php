<?php

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Models\Datacenter;
use App\Models\Discrepancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * Test discrepancy index page renders with data.
 */
it('renders discrepancy index page with data', function () {
    $datacenter = Datacenter::factory()->create();

    // Create some discrepancies
    Discrepancy::factory()->count(3)->create([
        'datacenter_id' => $datacenter->id,
        'status' => DiscrepancyStatus::Open,
    ]);

    $response = $this->get('/discrepancies');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Discrepancies/Index')
        ->has('discrepancies')
        ->has('discrepancies.data', 3)
        ->has('summary')
        ->has('filters')
        ->has('datacenters')
    );
});

/**
 * Test filter controls update query parameters.
 */
it('filters discrepancies by query parameters', function () {
    $datacenter = Datacenter::factory()->create();

    // Create discrepancies of different types
    Discrepancy::factory()->create([
        'datacenter_id' => $datacenter->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'status' => DiscrepancyStatus::Open,
    ]);

    Discrepancy::factory()->create([
        'datacenter_id' => $datacenter->id,
        'discrepancy_type' => DiscrepancyType::Unexpected,
        'status' => DiscrepancyStatus::Open,
    ]);

    // Filter by type
    $response = $this->get('/discrepancies?discrepancy_type=missing');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Discrepancies/Index')
        ->has('discrepancies.data', 1)
        ->where('filters.discrepancy_type', 'missing')
    );

    // Filter by status
    $response = $this->get('/discrepancies?status=open');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->where('filters.status', 'open')
    );

    // Filter by datacenter
    $response = $this->get('/discrepancies?datacenter_id='.$datacenter->id);

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->where('filters.datacenter_id', (string) $datacenter->id)
    );
});

/**
 * Test "Run Detection Now" button triggers detection.
 */
it('triggers on-demand detection via API', function () {
    $datacenter = Datacenter::factory()->create();

    $response = $this->postJson('/api/discrepancies/detect', [
        'datacenter_id' => $datacenter->id,
    ]);

    $response->assertStatus(202);
    $response->assertJson([
        'message' => 'Detection job dispatched successfully.',
    ]);
});

/**
 * Test discrepancy summary shows correct counts.
 */
it('includes summary statistics in page props', function () {
    $datacenter = Datacenter::factory()->create();

    // Create discrepancies of different types
    Discrepancy::factory()->count(2)->create([
        'datacenter_id' => $datacenter->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'status' => DiscrepancyStatus::Open,
    ]);

    Discrepancy::factory()->create([
        'datacenter_id' => $datacenter->id,
        'discrepancy_type' => DiscrepancyType::Unexpected,
        'status' => DiscrepancyStatus::Open,
    ]);

    $response = $this->get('/discrepancies');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Discrepancies/Index')
        ->has('summary')
        ->has('summary.by_type')
        ->has('summary.by_datacenter')
        ->where('summary.total', 3)
    );
});

/**
 * Test discrepancy index page shows empty state when no discrepancies.
 */
it('shows empty state when no discrepancies exist', function () {
    $response = $this->get('/discrepancies');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Discrepancies/Index')
        ->has('discrepancies.data', 0)
        ->where('summary.total', 0)
    );
});

/**
 * Test datacenter discrepancy summary endpoint.
 */
it('provides datacenter-filtered summary data', function () {
    $datacenter1 = Datacenter::factory()->create();
    $datacenter2 = Datacenter::factory()->create();

    // Create discrepancies for different datacenters
    Discrepancy::factory()->count(3)->create([
        'datacenter_id' => $datacenter1->id,
        'status' => DiscrepancyStatus::Open,
    ]);

    Discrepancy::factory()->create([
        'datacenter_id' => $datacenter2->id,
        'status' => DiscrepancyStatus::Open,
    ]);

    // Filter by datacenter
    $response = $this->get('/discrepancies?datacenter_id='.$datacenter1->id);

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Discrepancies/Index')
        ->has('discrepancies.data', 3)
    );
});

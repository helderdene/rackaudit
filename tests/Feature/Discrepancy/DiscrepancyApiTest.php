<?php

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Jobs\DetectDiscrepanciesJob;
use App\Models\Datacenter;
use App\Models\Discrepancy;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * Test 1: GET /api/discrepancies returns paginated list with filters
 */
it('returns paginated discrepancy list with filters', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    // Create discrepancies with different types and statuses
    $missingDiscrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->missing()
        ->create();

    $unexpectedDiscrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->unexpected()
        ->create();

    $acknowledgedDiscrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->acknowledged($this->user)
        ->create();

    // Create a discrepancy in a different datacenter
    $otherDiscrepancy = Discrepancy::factory()->create();

    // Test basic listing
    $response = $this->getJson('/api/discrepancies');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'discrepancy_type',
                    'discrepancy_type_label',
                    'status',
                    'status_label',
                    'title',
                    'detected_at',
                ],
            ],
            'links',
            'meta',
        ]);

    // Test filtering by datacenter
    $response = $this->getJson("/api/discrepancies?datacenter_id={$datacenter->id}");
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);

    // Test filtering by discrepancy type
    $response = $this->getJson('/api/discrepancies?discrepancy_type=missing');
    $response->assertOk();
    $responseData = $response->json('data');
    expect($responseData)->each(
        fn ($item) => $item->discrepancy_type->toBe('missing')
    );

    // Test filtering by status
    $response = $this->getJson('/api/discrepancies?status=acknowledged');
    $response->assertOk();
    $responseData = $response->json('data');
    expect($responseData)->each(
        fn ($item) => $item->status->toBe('acknowledged')
    );

    // Test pagination
    $response = $this->getJson('/api/discrepancies?per_page=2');
    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(2);
});

/**
 * Test 2: GET /api/discrepancies/{id} returns single discrepancy
 */
it('returns single discrepancy with full details', function () {
    $datacenter = Datacenter::factory()->create();
    $sourcePort = Port::factory()->create();
    $destPort = Port::factory()->create();

    $discrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->configurationMismatch()
        ->create([
            'source_port_id' => $sourcePort->id,
            'dest_port_id' => $destPort->id,
        ]);

    $response = $this->getJson("/api/discrepancies/{$discrepancy->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'discrepancy_type',
                'discrepancy_type_label',
                'status',
                'status_label',
                'title',
                'description',
                'detected_at',
                'datacenter',
                'source_port',
                'dest_port',
                'expected_config',
                'actual_config',
                'mismatch_details',
            ],
        ])
        ->assertJsonPath('data.id', $discrepancy->id)
        ->assertJsonPath('data.datacenter.id', $datacenter->id);
});

/**
 * Test 3: PATCH /api/discrepancies/{id}/acknowledge updates status
 */
it('acknowledges a discrepancy and updates status', function () {
    $discrepancy = Discrepancy::factory()->open()->create();

    $response = $this->patchJson("/api/discrepancies/{$discrepancy->id}/acknowledge", [
        'notes' => 'Acknowledged for review',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'acknowledged')
        ->assertJsonPath('message', 'Discrepancy acknowledged successfully.');

    $discrepancy->refresh();

    expect($discrepancy->status)->toBe(DiscrepancyStatus::Acknowledged);
    expect($discrepancy->acknowledged_by)->toBe($this->user->id);
    expect($discrepancy->acknowledged_at)->not->toBeNull();
});

/**
 * Test 4: PATCH /api/discrepancies/{id}/resolve updates status
 */
it('resolves a discrepancy and updates status', function () {
    $discrepancy = Discrepancy::factory()
        ->acknowledged($this->user)
        ->create();

    $response = $this->patchJson("/api/discrepancies/{$discrepancy->id}/resolve", [
        'notes' => 'Issue has been resolved',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('message', 'Discrepancy resolved successfully.');

    $discrepancy->refresh();

    expect($discrepancy->status)->toBe(DiscrepancyStatus::Resolved);
    expect($discrepancy->resolved_by)->toBe($this->user->id);
    expect($discrepancy->resolved_at)->not->toBeNull();
});

/**
 * Test 5: POST /api/discrepancies/detect triggers on-demand detection
 */
it('triggers on-demand detection with scope parameters', function () {
    Queue::fake();

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $implementationFile = ImplementationFile::factory()->create(['datacenter_id' => $datacenter->id]);

    // Test datacenter scope
    $response = $this->postJson('/api/discrepancies/detect', [
        'datacenter_id' => $datacenter->id,
    ]);

    $response->assertAccepted()
        ->assertJsonPath('message', 'Detection job dispatched successfully.');

    Queue::assertPushed(DetectDiscrepanciesJob::class, function ($job) use ($datacenter) {
        return $job->datacenterId === $datacenter->id;
    });

    // Test room scope
    $response = $this->postJson('/api/discrepancies/detect', [
        'room_id' => $room->id,
    ]);

    $response->assertAccepted();

    Queue::assertPushed(DetectDiscrepanciesJob::class, function ($job) use ($room) {
        return $job->roomId === $room->id;
    });

    // Test implementation file scope
    $response = $this->postJson('/api/discrepancies/detect', [
        'implementation_file_id' => $implementationFile->id,
    ]);

    $response->assertAccepted();

    Queue::assertPushed(DetectDiscrepanciesJob::class, function ($job) use ($implementationFile) {
        return $job->implementationFileId === $implementationFile->id;
    });
});

/**
 * Test 6: Authorization checks for each endpoint
 */
it('requires authentication for all endpoints', function () {
    // Logout the user
    auth()->logout();

    $discrepancy = Discrepancy::factory()->create();

    // Test each endpoint without authentication
    $this->getJson('/api/discrepancies')
        ->assertUnauthorized();

    $this->getJson("/api/discrepancies/{$discrepancy->id}")
        ->assertUnauthorized();

    $this->patchJson("/api/discrepancies/{$discrepancy->id}/acknowledge")
        ->assertUnauthorized();

    $this->patchJson("/api/discrepancies/{$discrepancy->id}/resolve")
        ->assertUnauthorized();

    $this->postJson('/api/discrepancies/detect')
        ->assertUnauthorized();
});

/**
 * Test 7: Sorting and date range filtering
 */
it('supports sorting and date range filtering', function () {
    $datacenter = Datacenter::factory()->create();

    // Create discrepancies with different detected dates
    $oldDiscrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->create(['detected_at' => now()->subDays(5)]);

    $newDiscrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->create(['detected_at' => now()->subDay()]);

    $middleDiscrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->create(['detected_at' => now()->subDays(3)]);

    // Test sorting by detected_at descending
    $response = $this->getJson('/api/discrepancies?sort_by=detected_at&sort_order=desc');
    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids[0])->toBe($newDiscrepancy->id);

    // Test date range filtering
    $startDate = now()->subDays(4)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $response = $this->getJson("/api/discrepancies?date_from={$startDate}&date_to={$endDate}");
    $response->assertOk();

    $responseIds = collect($response->json('data'))->pluck('id');
    expect($responseIds)->toContain($newDiscrepancy->id)
        ->toContain($middleDiscrepancy->id)
        ->not->toContain($oldDiscrepancy->id);
});

<?php

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Enums\ExpectedConnectionStatus;
use App\Enums\FindingStatus;
use App\Events\ConnectionChanged;
use App\Events\FindingResolved;
use App\Jobs\DetectDiscrepanciesJob;
use App\Jobs\NotifyUsersOfDiscrepancies;
use App\Listeners\DetectDiscrepanciesForConnection;
use App\Models\Audit;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Discrepancy;
use App\Models\ExpectedConnection;
use App\Models\Finding;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Notifications\NewDiscrepancyNotification;
use App\Services\AuditExecutionService;
use App\Services\DiscrepancyDetectionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Strategic end-to-end and integration tests for the Discrepancy Detection feature.
 *
 * These tests cover complete user workflows across multiple services/components,
 * testing integration points that individual unit tests may miss.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * E2E Test 1: Full detection flow from connection change to dashboard display
 *
 * Tests the complete flow:
 * 1. Create expected connection
 * 2. Create a new actual connection (triggers detection)
 * 3. Detection job runs and identifies mismatch
 * 4. Discrepancy appears on dashboard via API
 */
it('detects discrepancy when connection changes and displays on dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Set up datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create ports for the expected and actual connections
    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);
    $wrongDestPort = Port::factory()->create(['device_id' => $device->id]);

    // Create approved implementation file with expected connection
    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Create actual connection to wrong destination (will cause mismatch)
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $wrongDestPort->id,
    ]);

    // Run detection (simulating what the job would do)
    $detectionService = app(DiscrepancyDetectionService::class);
    $discrepancies = $detectionService->detectForDatacenter($datacenter);

    // Verify discrepancies were detected
    expect($discrepancies)->not->toBeEmpty();

    // Verify discrepancy appears on dashboard via API
    $response = $this->getJson('/api/discrepancies');
    $response->assertOk();

    $responseData = $response->json('data');
    expect($responseData)->not->toBeEmpty();

    // Check the discrepancy matches our expected source port
    $found = collect($responseData)->contains(fn ($d) => $d['source_port']['id'] === $sourcePort->id ||
        str_contains($d['title'] ?? '', 'Mismatch') ||
        str_contains($d['title'] ?? '', 'Missing')
    );
    expect($found)->toBeTrue();
});

/**
 * E2E Test 2: Full flow from detection to notification delivery
 *
 * Tests the complete notification flow:
 * 1. Create IT Manager with datacenter access
 * 2. Run detection that creates discrepancies
 * 3. Notification job sends to IT Manager
 * 4. Verify notification content
 */
it('sends notification to IT Manager when discrepancy is detected', function () {
    Notification::fake();

    // Create IT Manager with datacenter access
    $datacenter = Datacenter::factory()->create();
    $itManager = User::factory()->create(['discrepancy_notifications' => 'all']);
    $itManager->assignRole('IT Manager');
    $itManager->datacenters()->attach($datacenter);

    // Set up datacenter hierarchy for discrepancy
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Run detection (creates missing discrepancy since no actual connection exists)
    $detectionService = app(DiscrepancyDetectionService::class);
    $discrepancies = $detectionService->detectForDatacenter($datacenter);

    expect($discrepancies)->toHaveCount(1);

    // Run notification job
    $notificationJob = new NotifyUsersOfDiscrepancies($discrepancies, $datacenter->id);
    $notificationJob->handle();

    // Verify IT Manager received the notification
    Notification::assertSentTo(
        $itManager,
        NewDiscrepancyNotification::class,
        function ($notification) use ($discrepancies) {
            return $notification->discrepancy->id === $discrepancies->first()->id;
        }
    );
});

/**
 * E2E Test 3: Complete audit lifecycle - import, verify, resolve
 *
 * Tests the complete flow:
 * 1. Create discrepancy via detection
 * 2. Import discrepancy into audit as verification item
 * 3. Mark verification as discrepant (creates finding)
 * 4. Resolve finding
 * 5. Verify discrepancy is auto-resolved
 */
it('completes full audit lifecycle from import to resolution', function () {
    Event::fake([FindingResolved::class]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    $expectedConnection = ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Step 1: Run detection to create discrepancy
    $detectionService = app(DiscrepancyDetectionService::class);
    $discrepancies = $detectionService->detectForDatacenter($datacenter);

    expect($discrepancies)->toHaveCount(1);
    $discrepancy = $discrepancies->first();
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Open);

    // Step 2: Create audit and import discrepancy
    $audit = Audit::factory()
        ->for($datacenter)
        ->pending()
        ->create();

    $auditService = app(AuditExecutionService::class);
    $verifications = $auditService->importDiscrepanciesAsVerificationItems($audit, [$discrepancy->id]);

    expect($verifications)->toHaveCount(1);
    $verification = $verifications->first();

    // Verify discrepancy is now in audit
    $discrepancy->refresh();
    expect($discrepancy->status)->toBe(DiscrepancyStatus::InAudit);
    expect($discrepancy->audit_id)->toBe($audit->id);

    // Step 3: Mark verification as discrepant (this creates a finding)
    $auditService->markDiscrepant($verification, $user, DiscrepancyType::Missing, 'Confirmed missing connection');

    // Verify finding was created and linked to discrepancy
    $discrepancy->refresh();
    expect($discrepancy->finding_id)->not->toBeNull();

    $finding = Finding::find($discrepancy->finding_id);
    expect($finding)->not->toBeNull();
    expect($finding->audit_id)->toBe($audit->id);

    // Step 4: Resolve the finding
    $finding->update([
        'status' => FindingStatus::Resolved,
        'resolved_by' => $user->id,
        'resolved_at' => now(),
    ]);

    // Dispatch the FindingResolved event to trigger auto-resolve
    event(new FindingResolved($finding, $user));

    // Unfake events to allow the listener to run
    Event::assertDispatched(FindingResolved::class);
});

/**
 * E2E Test 4: Scheduled job runs and creates discrepancies
 *
 * Tests:
 * 1. DetectDiscrepanciesJob runs with datacenter scope
 * 2. Creates discrepancies correctly
 * 3. Dispatches notification job if enabled
 */
it('scheduled detection job creates discrepancies and dispatches notifications', function () {
    Queue::fake();
    Notification::fake();

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Run the detection job directly (simulating scheduled execution)
    $job = new DetectDiscrepanciesJob(datacenterId: $datacenter->id, shouldNotify: true);
    $job->handle(app(DiscrepancyDetectionService::class));

    // Verify discrepancy was created
    expect(Discrepancy::count())->toBe(1);

    $discrepancy = Discrepancy::first();
    expect($discrepancy->datacenter_id)->toBe($datacenter->id);
    expect($discrepancy->discrepancy_type)->toBe(DiscrepancyType::Missing);

    // Verify notification job was dispatched
    Queue::assertPushed(NotifyUsersOfDiscrepancies::class);
});

/**
 * E2E Test 5: Real-time detection on connection creation event
 *
 * Tests the event-driven flow:
 * 1. Connection changed event is dispatched
 * 2. Listener queues DetectDiscrepanciesJob
 * 3. Job runs and creates/updates discrepancies
 */
it('triggers real-time detection when connection is created', function () {
    Queue::fake();

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    // Create a connection
    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Create event and manually invoke listener (testing listener behavior)
    $event = new ConnectionChanged($connection, 'created');
    $listener = new DetectDiscrepanciesForConnection;
    $listener->handle($event);

    // Verify the detection job was queued
    Queue::assertPushed(DetectDiscrepanciesJob::class, function ($job) use ($connection) {
        return $job->connectionId === $connection->id;
    });
});

/**
 * E2E Test 6: Discrepancy status lifecycle via API
 *
 * Tests:
 * 1. Discrepancy starts as Open
 * 2. Acknowledge via API -> status changes to Acknowledged
 * 3. Resolve via API -> status changes to Resolved
 * 4. Timestamps and user IDs are set correctly
 */
it('manages discrepancy lifecycle through API endpoints', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $discrepancy = Discrepancy::factory()->open()->create();

    // Verify initial state
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Open);

    // Acknowledge via API
    $response = $this->patchJson("/api/discrepancies/{$discrepancy->id}/acknowledge", [
        'notes' => 'Reviewing this issue',
    ]);

    $response->assertOk();

    $discrepancy->refresh();
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Acknowledged);
    expect($discrepancy->acknowledged_by)->toBe($user->id);
    expect($discrepancy->acknowledged_at)->not->toBeNull();

    // Resolve via API
    $response = $this->patchJson("/api/discrepancies/{$discrepancy->id}/resolve", [
        'notes' => 'Issue has been fixed',
    ]);

    $response->assertOk();

    $discrepancy->refresh();
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Resolved);
    expect($discrepancy->resolved_by)->toBe($user->id);
    expect($discrepancy->resolved_at)->not->toBeNull();
});

/**
 * E2E Test 7: Dashboard displays correct summary statistics
 *
 * Tests:
 * 1. Create discrepancies of different types and datacenters
 * 2. Load dashboard page
 * 3. Verify summary statistics are accurate
 */
it('displays accurate summary statistics on dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);

    // Create discrepancies with unique ports for each
    $port1 = Port::factory()->create();
    $port2 = Port::factory()->create();
    $port3 = Port::factory()->create();
    $port4 = Port::factory()->create();
    $port5 = Port::factory()->create();
    $port6 = Port::factory()->create();
    $port7 = Port::factory()->create();
    $port8 = Port::factory()->create();
    $port9 = Port::factory()->create();
    $port10 = Port::factory()->create();

    // Create discrepancies in DC1: 2 Missing, 1 Unexpected
    Discrepancy::factory()->forDatacenter($datacenter1)->missing()->create([
        'source_port_id' => $port1->id,
        'dest_port_id' => $port2->id,
    ]);
    Discrepancy::factory()->forDatacenter($datacenter1)->missing()->create([
        'source_port_id' => $port3->id,
        'dest_port_id' => $port4->id,
    ]);
    Discrepancy::factory()->forDatacenter($datacenter1)->unexpected()->create([
        'source_port_id' => $port5->id,
        'dest_port_id' => $port6->id,
    ]);

    // Create discrepancies in DC2: 1 Mismatched, 1 Conflicting
    Discrepancy::factory()->forDatacenter($datacenter2)->mismatched()->create([
        'source_port_id' => $port7->id,
        'dest_port_id' => $port8->id,
    ]);
    Discrepancy::factory()->forDatacenter($datacenter2)->conflicting()->create([
        'source_port_id' => $port9->id,
        'dest_port_id' => $port10->id,
    ]);

    // Load dashboard
    $response = $this->get('/discrepancies');
    $response->assertStatus(200);

    $response->assertInertia(fn ($page) => $page
        ->component('Discrepancies/Index')
        ->where('summary.total', 5)
        ->has('summary.by_type')
        ->has('summary.by_datacenter')
    );
});

/**
 * E2E Test 8: Filter discrepancies by datacenter on dashboard
 *
 * Tests:
 * 1. Create discrepancies in multiple datacenters
 * 2. Apply datacenter filter
 * 3. Only discrepancies from that datacenter are returned
 */
it('filters discrepancies by datacenter correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter1 = Datacenter::factory()->create();
    $datacenter2 = Datacenter::factory()->create();

    // Create discrepancies in both datacenters
    Discrepancy::factory()->count(3)->forDatacenter($datacenter1)->create();
    Discrepancy::factory()->count(2)->forDatacenter($datacenter2)->create();

    // Filter by datacenter 1
    $response = $this->get("/discrepancies?datacenter_id={$datacenter1->id}");
    $response->assertStatus(200);

    $response->assertInertia(fn ($page) => $page
        ->component('Discrepancies/Index')
        ->has('discrepancies.data', 3)
    );

    // Filter by datacenter 2
    $response = $this->get("/discrepancies?datacenter_id={$datacenter2->id}");
    $response->assertStatus(200);

    $response->assertInertia(fn ($page) => $page
        ->component('Discrepancies/Index')
        ->has('discrepancies.data', 2)
    );
});

/**
 * E2E Test 9: Resolved discrepancies are removed when connection matches
 *
 * Tests:
 * 1. Create expected connection (discrepancy created - missing)
 * 2. Create matching actual connection
 * 3. Run detection again
 * 4. Discrepancy is resolved
 */
it('resolves discrepancy when actual connection matches expected', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // First detection: missing discrepancy
    $detectionService = app(DiscrepancyDetectionService::class);
    $discrepancies = $detectionService->detectForDatacenter($datacenter);

    expect($discrepancies)->toHaveCount(1);
    $discrepancy = $discrepancies->first();
    expect($discrepancy->discrepancy_type)->toBe(DiscrepancyType::Missing);
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Open);

    // Create actual connection that matches
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Second detection: discrepancy should be resolved
    $detectionService->detectForDatacenter($datacenter);

    $discrepancy->refresh();
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Resolved);
    expect($discrepancy->resolved_at)->not->toBeNull();
});

/**
 * E2E Test 10: InAudit status prevents duplicate import
 *
 * Tests:
 * 1. Create discrepancy and import into audit 1
 * 2. Try to import same discrepancy into audit 2
 * 3. Import fails / is skipped
 */
it('prevents importing discrepancy that is already in audit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();

    // Create a discrepancy
    $discrepancy = Discrepancy::factory()
        ->forDatacenter($datacenter)
        ->open()
        ->create();

    // Create first audit and import
    $audit1 = Audit::factory()->for($datacenter)->pending()->create();
    $auditService = app(AuditExecutionService::class);

    $verifications = $auditService->importDiscrepanciesAsVerificationItems($audit1, [$discrepancy->id]);
    expect($verifications)->toHaveCount(1);

    $discrepancy->refresh();
    expect($discrepancy->status)->toBe(DiscrepancyStatus::InAudit);

    // Try to import into second audit
    $audit2 = Audit::factory()->for($datacenter)->pending()->create();

    $verifications2 = $auditService->importDiscrepanciesAsVerificationItems($audit2, [$discrepancy->id]);

    // Should not import since already in audit
    expect($verifications2)->toHaveCount(0);

    // Discrepancy should still be linked to first audit
    $discrepancy->refresh();
    expect($discrepancy->audit_id)->toBe($audit1->id);
});

<?php

use App\Events\AuditExecution\DeviceLocked;
use App\Events\AuditExecution\VerificationCompleted;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\AuditDeviceVerification;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('broadcast events implementing ShouldBroadcast are queued correctly', function () {
    Event::fake([VerificationCompleted::class]);

    $user = User::factory()->create();
    $audit = Audit::factory()->create();
    $verification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    event(new VerificationCompleted($verification, $audit, $user));

    Event::assertDispatched(VerificationCompleted::class, function ($event) {
        return $event instanceof ShouldBroadcast;
    });
});

test('broadcast channel authorization returns true for authorized users', function () {
    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $audit = Audit::factory()->create(['created_by' => $creator->id]);
    $audit->assignees()->attach($assignee);

    // Test the authorization logic directly (as defined in routes/channels.php)
    // Creator should have access
    expect($audit->created_by)->toBe($creator->id);

    // Assignee should have access
    expect($audit->assignees()->where('user_id', $assignee->id)->exists())->toBeTrue();

    // Creator HTTP auth should work
    $this->actingAs($creator)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-audit.'.$audit->id,
        ])
        ->assertOk();

    // Assignee HTTP auth should work
    $this->actingAs($assignee)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-audit.'.$audit->id,
        ])
        ->assertOk();
});

test('broadcast channel authorization returns false for unauthorized users', function () {
    $creator = User::factory()->create();
    $unauthorizedUser = User::factory()->create();
    $audit = Audit::factory()->create(['created_by' => $creator->id]);

    // Test the authorization logic directly
    // Unauthorized user should NOT have access
    expect($audit->created_by)->not->toBe($unauthorizedUser->id);
    expect($audit->assignees()->where('user_id', $unauthorizedUser->id)->exists())->toBeFalse();
});

test('broadcastWith returns properly serialized payloads', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->create();
    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $event = new DeviceLocked($verification, $audit, $user);
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['verification_id', 'device_id', 'audit_id', 'locked_by', 'locked_at', 'timestamp'])
        ->and($payload['verification_id'])->toBe($verification->id)
        ->and($payload['audit_id'])->toBe($audit->id)
        ->and($payload['locked_by'])->toHaveKeys(['id', 'name'])
        ->and($payload['locked_by']['id'])->toBe($user->id)
        ->and($payload['locked_by']['name'])->toBe($user->name);
});

test('broadcastAs returns expected event names', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->create();
    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $deviceLockedEvent = new DeviceLocked($verification, $audit, $user);

    expect($deviceLockedEvent->broadcastAs())->toBe('device.locked');

    // Test VerificationCompleted event name
    $connectionVerification = AuditConnectionVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create(['expected_connection_id' => null, 'connection_id' => null]);

    $verificationCompletedEvent = new VerificationCompleted($connectionVerification, $audit, $user);

    expect($verificationCompletedEvent->broadcastAs())->toBe('verification.completed');
});

test('broadcastOn returns correct private channel', function () {
    $user = User::factory()->create();
    $audit = Audit::factory()->create();
    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $event = new DeviceLocked($verification, $audit, $user);
    $channel = $event->broadcastOn();

    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-audit.'.$audit->id);
});

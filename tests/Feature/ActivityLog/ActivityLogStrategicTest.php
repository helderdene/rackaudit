<?php

/**
 * Strategic tests to fill coverage gaps for the Activity Logging feature.
 *
 * These tests cover:
 * - End-to-end workflows (user action -> activity log visible in UI)
 * - Authorization scenarios beyond basic role tests
 * - Edge cases for data handling
 */

use App\Models\ActivityLog;
use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check
    config(['inertia.testing.ensure_pages_exist' => false]);
});

describe('End-to-End Workflows', function () {
    it('shows activity log in UI when user creates a record', function () {
        $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
        $admin->assignRole('Administrator');

        // Clear any logs from setup
        ActivityLog::query()->delete();

        // Log in the admin user
        Auth::login($admin);

        // Create a new datacenter which will trigger the Loggable trait
        $datacenter = Datacenter::create([
            'name' => 'New Data Center',
            'address_line_1' => '123 Main Street',
            'city' => 'New York',
            'state_province' => 'NY',
            'postal_code' => '10001',
            'country' => 'United States',
            'primary_contact_name' => 'John Doe',
            'primary_contact_email' => 'john@example.com',
            'primary_contact_phone' => '+1-555-123-4567',
        ]);

        // Verify the datacenter creation activity log is visible
        $log = ActivityLog::where('subject_type', Datacenter::class)
            ->where('subject_id', $datacenter->id)
            ->where('action', 'created')
            ->first();

        expect($log)->not->toBeNull();
        expect($log->causer_id)->toBe($admin->id);

        // Verify it appears in the UI
        $response = $this->actingAs($admin)->get(route('activity-logs.index'));

        $response->assertOk();

        // Find the log in the response data using subject_type + subject_id + action
        $responseData = $response->original->getData();
        $logs = collect($responseData['page']['props']['activityLogs']['data']);
        $datacenterLog = $logs->first(fn ($log) =>
            $log['subject_type'] === Datacenter::class &&
            $log['subject_id'] === $datacenter->id &&
            $log['action'] === 'created'
        );

        expect($datacenterLog)->not->toBeNull();
        expect($datacenterLog['causer_name'])->toBe('Admin User');
    });

    it('shows changes in activity log when user updates a record', function () {
        $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
        $admin->assignRole('Administrator');

        // Create datacenter without triggering logging
        $datacenter = Datacenter::withoutEvents(fn () => Datacenter::factory()->create([
            'name' => 'Original Name',
        ]));

        // Clear any logs from setup
        ActivityLog::query()->delete();

        Auth::login($admin);

        // Update the datacenter - this will trigger the updated event
        $datacenter->update(['name' => 'Updated Name']);

        // Verify the update log was created
        $log = ActivityLog::where('subject_type', Datacenter::class)
            ->where('subject_id', $datacenter->id)
            ->where('action', 'updated')
            ->first();

        expect($log)->not->toBeNull();
        expect($log->old_values['name'])->toBe('Original Name');
        expect($log->new_values['name'])->toBe('Updated Name');

        // Verify it appears in the UI
        $response = $this->actingAs($admin)->get(route('activity-logs.index'));

        $response->assertOk();

        $responseData = $response->original->getData();
        $logs = collect($responseData['page']['props']['activityLogs']['data']);
        $updateLog = $logs->first(fn ($log) => $log['action'] === 'updated' && $log['subject_type'] === Datacenter::class);

        expect($updateLog)->not->toBeNull();
        expect($updateLog['old_values']['name'])->toBe('Original Name');
        expect($updateLog['new_values']['name'])->toBe('Updated Name');
    });
});

describe('Authorization Edge Cases', function () {
    it('viewer cannot see activity logs from other users even with filters', function () {
        $viewer = User::withoutEvents(fn () => User::factory()->create());
        $viewer->assignRole('Viewer');

        $otherUser = User::withoutEvents(fn () => User::factory()->create());

        // Clear any logs from setup
        ActivityLog::query()->delete();

        // Create logs by the viewer
        $viewerLog = ActivityLog::factory()->create([
            'causer_id' => $viewer->id,
            'new_values' => ['searchable' => 'viewer-owned'],
        ]);

        // Create logs by another user with same searchable value
        $otherLog = ActivityLog::factory()->create([
            'causer_id' => $otherUser->id,
            'new_values' => ['searchable' => 'viewer-owned'],
        ]);

        // Even with search filter, viewer should NOT see other user's logs
        $response = $this->actingAs($viewer)->get('/activity-logs?search=viewer-owned');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActivityLogs/Index')
                ->has('activityLogs.data', 1)
                ->where('activityLogs.data.0.id', $viewerLog->id)
            );
    });

    it('IT Manager sees logs within datacenter scope', function () {
        $itManager = User::withoutEvents(fn () => User::factory()->create());
        $itManager->assignRole('IT Manager');

        $userInDatacenter = User::withoutEvents(fn () => User::factory()->create());

        $datacenter = Datacenter::withoutEvents(fn () => Datacenter::factory()->create(['name' => 'DC1']));

        // Assign IT Manager and user to the same datacenter
        $itManager->datacenters()->attach($datacenter->id);
        $userInDatacenter->datacenters()->attach($datacenter->id);

        // Clear any logs from setup
        ActivityLog::query()->delete();

        // Create a log where subject is a user in the datacenter
        $logInScope = ActivityLog::factory()->create([
            'subject_type' => User::class,
            'subject_id' => $userInDatacenter->id,
            'causer_id' => User::withoutEvents(fn () => User::factory()->create())->id,
        ]);

        // Create a log for a user NOT in any datacenter
        $userNotInDatacenter = User::withoutEvents(fn () => User::factory()->create());
        $logOutOfScope = ActivityLog::factory()->create([
            'subject_type' => User::class,
            'subject_id' => $userNotInDatacenter->id,
            'causer_id' => User::withoutEvents(fn () => User::factory()->create())->id,
        ]);

        // IT Manager should see the log in scope
        $response = $this->actingAs($itManager)->get(route('activity-logs.index'));

        $response->assertOk();

        // Collect the log IDs the IT Manager can see
        $responseData = $response->original->getData();
        $visibleLogIds = collect($responseData['page']['props']['activityLogs']['data'])->pluck('id')->toArray();

        expect($visibleLogIds)->toContain($logInScope->id);
        expect($visibleLogIds)->not->toContain($logOutOfScope->id);
    });

    it('IT Manager can see datacenter activity logs', function () {
        $itManager = User::withoutEvents(fn () => User::factory()->create());
        $itManager->assignRole('IT Manager');

        $datacenter = Datacenter::withoutEvents(fn () => Datacenter::factory()->create(['name' => 'DC1']));

        // Assign IT Manager to the datacenter
        $itManager->datacenters()->attach($datacenter->id);

        // Clear any logs from setup
        ActivityLog::query()->delete();

        // Create a log where subject is the datacenter itself
        $datacenterLog = ActivityLog::factory()->create([
            'subject_type' => Datacenter::class,
            'subject_id' => $datacenter->id,
            'causer_id' => User::withoutEvents(fn () => User::factory()->create())->id,
        ]);

        // Create a log for a different datacenter
        $otherDatacenter = Datacenter::withoutEvents(fn () => Datacenter::factory()->create(['name' => 'DC2']));
        $otherLog = ActivityLog::factory()->create([
            'subject_type' => Datacenter::class,
            'subject_id' => $otherDatacenter->id,
            'causer_id' => User::withoutEvents(fn () => User::factory()->create())->id,
        ]);

        // IT Manager should see logs for their datacenter
        $response = $this->actingAs($itManager)->get(route('activity-logs.index'));

        $response->assertOk();

        $responseData = $response->original->getData();
        $visibleLogIds = collect($responseData['page']['props']['activityLogs']['data'])->pluck('id')->toArray();

        expect($visibleLogIds)->toContain($datacenterLog->id);
        expect($visibleLogIds)->not->toContain($otherLog->id);
    });
});

describe('Edge Cases', function () {
    it('handles activity log with null causer (system action)', function () {
        $admin = User::withoutEvents(fn () => User::factory()->create());
        $admin->assignRole('Administrator');

        // Clear any logs from setup
        ActivityLog::query()->delete();

        // Create an activity log with null causer (simulating a system action)
        $systemLog = ActivityLog::factory()->create([
            'causer_id' => null,
            'new_values' => ['status' => 'processed by system'],
        ]);

        $response = $this->actingAs($admin)->get(route('activity-logs.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActivityLogs/Index')
                ->has('activityLogs.data', 1)
                ->where('activityLogs.data.0.causer_id', null)
                ->where('activityLogs.data.0.causer_name', 'System')
            );
    });

    it('handles large old/new values JSON data', function () {
        $admin = User::withoutEvents(fn () => User::factory()->create());
        $admin->assignRole('Administrator');

        // Clear any logs from setup
        ActivityLog::query()->delete();

        // Create a large JSON payload
        $largeData = [];
        for ($i = 0; $i < 50; $i++) {
            $largeData["field_{$i}"] = str_repeat('a', 100); // 100 chars per field
        }

        $activityLog = ActivityLog::factory()->create([
            'old_values' => $largeData,
            'new_values' => array_merge($largeData, ['updated_field' => 'new value']),
        ]);

        // Verify it can be retrieved and rendered
        $response = $this->actingAs($admin)->get(route('activity-logs.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActivityLogs/Index')
                ->has('activityLogs.data', 1)
                ->has('activityLogs.data.0.old_values')
                ->has('activityLogs.data.0.new_values')
            );

        // Verify the data is correctly stored and retrieved
        $storedLog = ActivityLog::find($activityLog->id);
        expect($storedLog->old_values)->toHaveCount(50);
        expect($storedLog->new_values)->toHaveKey('updated_field');
    });

    it('correctly logs model deletion events', function () {
        $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
        $admin->assignRole('Administrator');

        // Create datacenter without triggering logging using the factory
        $datacenter = Datacenter::withoutEvents(fn () => Datacenter::factory()->create([
            'name' => 'Datacenter To Delete',
        ]));

        $datacenterId = $datacenter->id;

        // Clear any logs from setup
        ActivityLog::query()->delete();

        Auth::login($admin);

        // Delete the datacenter - this will trigger the deleted event
        $datacenter->delete();

        // Verify the delete log was created in the database
        $deleteLog = ActivityLog::where('subject_type', Datacenter::class)
            ->where('subject_id', $datacenterId)
            ->where('action', 'deleted')
            ->first();

        expect($deleteLog)->not->toBeNull();
        expect($deleteLog->old_values['name'])->toBe('Datacenter To Delete');
        expect($deleteLog->new_values)->toBeNull();

        // Verify it appears in the UI
        $response = $this->actingAs($admin)->get(route('activity-logs.index'));

        $response->assertOk();

        $responseData = $response->original->getData();
        $logs = collect($responseData['page']['props']['activityLogs']['data']);
        $deletedLog = $logs->first(fn ($log) => $log['action'] === 'deleted' && $log['subject_type'] === Datacenter::class);

        expect($deletedLog)->not->toBeNull();
        expect($deletedLog['subject_id'])->toBe($datacenterId);
        expect($deletedLog['old_values']['name'])->toBe('Datacenter To Delete');
        expect($deletedLog['new_values'])->toBeNull();
    });
});

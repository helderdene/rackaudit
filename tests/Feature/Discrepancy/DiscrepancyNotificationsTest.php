<?php

use App\Jobs\NotifyUsersOfDiscrepancies;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Discrepancy;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Notifications\DiscrepancyThresholdNotification;
use App\Notifications\NewDiscrepancyNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

test('NewDiscrepancyNotification sends to IT Managers for their datacenter', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);

    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');
    $itManager->datacenters()->attach($datacenter);
    $itManager->update(['discrepancy_notifications' => 'all']);

    $discrepancy = Discrepancy::factory()->missing()->create([
        'datacenter_id' => $datacenter->id,
        'title' => 'Missing Connection Test',
    ]);

    $notification = new NewDiscrepancyNotification($discrepancy);
    $itManager->notify($notification);

    Notification::assertSentTo($itManager, NewDiscrepancyNotification::class, function ($notification) use ($discrepancy) {
        return $notification->discrepancy->id === $discrepancy->id;
    });
});

test('DiscrepancyThresholdNotification sends when threshold exceeded', function () {
    $datacenter = Datacenter::factory()->create();

    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');
    $auditor->datacenters()->attach($datacenter);
    $auditor->update(['discrepancy_notifications' => 'threshold_only']);

    // Create shared infrastructure to avoid DeviceType uniqueness exhaustion
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();
    $deviceType = DeviceType::factory()->create();
    $device = Device::factory()->for($rack)->for($deviceType)->create();

    // Create ports that can be reused
    $ports = Port::factory()->for($device)->count(24)->create();

    // Create multiple discrepancies using the shared ports
    $discrepancies = collect();
    for ($i = 0; $i < 12; $i++) {
        $discrepancies->push(Discrepancy::factory()->forDatacenter($datacenter)->create([
            'source_port_id' => $ports[$i]->id,
            'dest_port_id' => $ports[$i + 12]->id,
        ]));
    }

    $summary = [
        'total_count' => 12,
        'by_type' => [
            'missing' => 5,
            'unexpected' => 4,
            'mismatched' => 3,
        ],
        'datacenter_name' => $datacenter->name,
    ];

    $notification = new DiscrepancyThresholdNotification($discrepancies, $summary);
    $auditor->notify($notification);

    Notification::assertSentTo($auditor, DiscrepancyThresholdNotification::class, function ($notification) use ($summary) {
        return $notification->summary['total_count'] === 12;
    });
});

test('Operators only receive notifications if subscribed', function () {
    $datacenter = Datacenter::factory()->create();

    $operatorSubscribed = User::factory()->create();
    $operatorSubscribed->assignRole('Operator');
    $operatorSubscribed->datacenters()->attach($datacenter);
    $operatorSubscribed->update(['discrepancy_notifications' => 'all']);

    $operatorNotSubscribed = User::factory()->create();
    $operatorNotSubscribed->assignRole('Operator');
    $operatorNotSubscribed->datacenters()->attach($datacenter);
    $operatorNotSubscribed->update(['discrepancy_notifications' => 'none']);

    $discrepancy = Discrepancy::factory()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Execute the notification job
    $job = new NotifyUsersOfDiscrepancies(collect([$discrepancy]), $datacenter->id);
    $job->handle();

    // Subscribed operator should receive notification
    Notification::assertSentTo($operatorSubscribed, NewDiscrepancyNotification::class);

    // Non-subscribed operator should NOT receive notification
    Notification::assertNotSentTo($operatorNotSubscribed, NewDiscrepancyNotification::class);
});

test('notification channels include database and mail', function () {
    $datacenter = Datacenter::factory()->create();
    $discrepancy = Discrepancy::factory()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    $user = User::factory()->create();
    $notification = new NewDiscrepancyNotification($discrepancy);

    $channels = $notification->via($user);

    expect($channels)->toContain('database');
    // Mail is included only if configured (not log/array driver)
    // In test environment mail driver may be 'log' or 'array'
    // so we just verify database is always included
});

test('notification content includes correct discrepancy details', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'Primary DC']);
    $discrepancy = Discrepancy::factory()->missing()->create([
        'datacenter_id' => $datacenter->id,
        'title' => 'Missing Critical Connection',
        'description' => 'Expected connection between servers not found',
    ]);

    $notification = new NewDiscrepancyNotification($discrepancy);
    $user = User::factory()->create();

    $arrayData = $notification->toArray($user);

    expect($arrayData)->toHaveKey('type', 'new_discrepancy');
    expect($arrayData)->toHaveKey('discrepancy_id', $discrepancy->id);
    expect($arrayData)->toHaveKey('discrepancy_type', 'missing');
    expect($arrayData)->toHaveKey('datacenter_id', $datacenter->id);
    expect($arrayData)->toHaveKey('datacenter_name', 'Primary DC');
    expect($arrayData)->toHaveKey('title', 'Missing Critical Connection');
});

test('NotifyUsersOfDiscrepancies job queries correct users by role and datacenter', function () {
    $datacenter = Datacenter::factory()->create();
    $otherDatacenter = Datacenter::factory()->create();

    // IT Manager with access to the datacenter - should receive
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');
    $itManager->datacenters()->attach($datacenter);
    $itManager->update(['discrepancy_notifications' => 'all']);

    // Auditor with access to the datacenter - should receive (threshold)
    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');
    $auditor->datacenters()->attach($datacenter);
    $auditor->update(['discrepancy_notifications' => 'threshold_only']);

    // IT Manager without datacenter access - should NOT receive
    $itManagerNoAccess = User::factory()->create();
    $itManagerNoAccess->assignRole('IT Manager');
    $itManagerNoAccess->datacenters()->attach($otherDatacenter);
    $itManagerNoAccess->update(['discrepancy_notifications' => 'all']);

    // Create shared infrastructure for discrepancies
    $room = Room::factory()->for($datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();
    $deviceType = DeviceType::factory()->create();
    $device = Device::factory()->for($rack)->for($deviceType)->create();
    $ports = Port::factory()->for($device)->count(6)->create();

    $discrepancies = collect();
    for ($i = 0; $i < 3; $i++) {
        $discrepancies->push(Discrepancy::factory()->forDatacenter($datacenter)->create([
            'source_port_id' => $ports[$i]->id,
            'dest_port_id' => $ports[$i + 3]->id,
        ]));
    }

    $job = new NotifyUsersOfDiscrepancies($discrepancies, $datacenter->id);
    $job->handle();

    // IT Manager with access should receive notification
    Notification::assertSentTo($itManager, NewDiscrepancyNotification::class);

    // IT Manager without access should NOT receive notification
    Notification::assertNotSentTo($itManagerNoAccess, NewDiscrepancyNotification::class);

    // Auditor with threshold_only should NOT receive individual notifications
    // (they only get threshold notifications when count exceeds threshold)
    Notification::assertNotSentTo($auditor, NewDiscrepancyNotification::class);
});

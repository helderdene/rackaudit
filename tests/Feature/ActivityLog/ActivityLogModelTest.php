<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear any activity logs that might be created during user factory setup
    ActivityLog::query()->delete();
});

test('activity log can be created with required fields', function () {
    $user = User::withoutEvents(fn () => User::factory()->create());

    $activityLog = ActivityLog::create([
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'causer_id' => $user->id,
        'action' => 'created',
        'old_values' => null,
        'new_values' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
    ]);

    expect($activityLog)->toBeInstanceOf(ActivityLog::class);
    expect($activityLog->subject_type)->toBe(User::class);
    expect($activityLog->subject_id)->toBe($user->id);
    expect($activityLog->causer_id)->toBe($user->id);
    expect($activityLog->action)->toBe('created');
    expect($activityLog->ip_address)->toBe('192.168.1.1');
});

test('polymorphic subject relationship resolves correctly', function () {
    $user = User::withoutEvents(fn () => User::factory()->create());

    $activityLog = ActivityLog::factory()->create([
        'subject_type' => User::class,
        'subject_id' => $user->id,
    ]);

    expect($activityLog->subject)->toBeInstanceOf(User::class);
    expect($activityLog->subject->id)->toBe($user->id);
});

test('causer relationship returns User model', function () {
    $causer = User::withoutEvents(fn () => User::factory()->create());
    $subject = User::withoutEvents(fn () => User::factory()->create());

    $activityLog = ActivityLog::factory()->create([
        'subject_type' => User::class,
        'subject_id' => $subject->id,
        'causer_id' => $causer->id,
    ]);

    expect($activityLog->causer)->toBeInstanceOf(User::class);
    expect($activityLog->causer->id)->toBe($causer->id);
});

test('old_values and new_values cast to arrays', function () {
    $user = User::withoutEvents(fn () => User::factory()->create());

    $activityLog = ActivityLog::factory()->create([
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'New Name'],
    ]);

    expect($activityLog->old_values)->toBeArray();
    expect($activityLog->new_values)->toBeArray();
    expect($activityLog->old_values)->toBe(['name' => 'Old Name']);
    expect($activityLog->new_values)->toBe(['name' => 'New Name']);
});

test('scopeForSubject filters by polymorphic subject', function () {
    $user1 = User::withoutEvents(fn () => User::factory()->create());
    $user2 = User::withoutEvents(fn () => User::factory()->create());

    ActivityLog::factory()->create([
        'subject_type' => User::class,
        'subject_id' => $user1->id,
    ]);
    ActivityLog::factory()->create([
        'subject_type' => User::class,
        'subject_id' => $user2->id,
    ]);

    $logs = ActivityLog::forSubject($user1)->get();

    expect($logs)->toHaveCount(1);
    expect($logs->first()->subject_id)->toBe($user1->id);
});

test('scopeByUser filters by causer_id', function () {
    $user1 = User::withoutEvents(fn () => User::factory()->create());
    $user2 = User::withoutEvents(fn () => User::factory()->create());

    ActivityLog::factory()->create(['causer_id' => $user1->id]);
    ActivityLog::factory()->create(['causer_id' => $user2->id]);
    ActivityLog::factory()->create(['causer_id' => $user1->id]);

    $logs = ActivityLog::byUser($user1->id)->get();

    expect($logs)->toHaveCount(2);
    expect($logs->pluck('causer_id')->unique()->toArray())->toBe([$user1->id]);
});

test('scopeByAction filters by action type', function () {
    ActivityLog::factory()->created()->create();
    ActivityLog::factory()->updated()->create();
    ActivityLog::factory()->deleted()->create();
    ActivityLog::factory()->created()->create();

    $createdLogs = ActivityLog::byAction('created')->get();
    $updatedLogs = ActivityLog::byAction('updated')->get();
    $deletedLogs = ActivityLog::byAction('deleted')->get();

    expect($createdLogs)->toHaveCount(2);
    expect($updatedLogs)->toHaveCount(1);
    expect($deletedLogs)->toHaveCount(1);
});

test('scopeInDateRange filters by created_at range', function () {
    ActivityLog::factory()->create(['created_at' => now()->subDays(10)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(5)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(2)]);
    ActivityLog::factory()->create(['created_at' => now()]);

    $logsWithStartDate = ActivityLog::inDateRange(now()->subDays(6)->toDateString(), null)->get();
    expect($logsWithStartDate)->toHaveCount(3);

    $logsWithEndDate = ActivityLog::inDateRange(null, now()->subDays(3)->toDateString())->get();
    expect($logsWithEndDate)->toHaveCount(2);

    $logsInRange = ActivityLog::inDateRange(
        now()->subDays(7)->toDateString(),
        now()->subDays(1)->toDateString()
    )->get();
    expect($logsInRange)->toHaveCount(2);

    $allLogs = ActivityLog::inDateRange(null, null)->get();
    expect($allLogs)->toHaveCount(4);
});

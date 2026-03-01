<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can have active status', function () {
    $user = User::factory()->create(['status' => 'active']);

    expect($user->status)->toBe('active');
    expect($user->isActive())->toBeTrue();
    expect($user->isInactive())->toBeFalse();
    expect($user->isSuspended())->toBeFalse();
});

test('user can have inactive status', function () {
    $user = User::factory()->inactive()->create();

    expect($user->status)->toBe('inactive');
    expect($user->isActive())->toBeFalse();
    expect($user->isInactive())->toBeTrue();
    expect($user->isSuspended())->toBeFalse();
});

test('user can have suspended status', function () {
    $user = User::factory()->suspended()->create();

    expect($user->status)->toBe('suspended');
    expect($user->isActive())->toBeFalse();
    expect($user->isInactive())->toBeFalse();
    expect($user->isSuspended())->toBeTrue();
});

test('user has last_active_at timestamp', function () {
    $timestamp = now()->subHours(2);
    $user = User::factory()->create(['last_active_at' => $timestamp]);

    expect($user->last_active_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($user->last_active_at->format('Y-m-d H:i'))->toBe($timestamp->format('Y-m-d H:i'));
});

test('user can have null last_active_at for never logged in users', function () {
    $user = User::factory()->create(['last_active_at' => null]);

    expect($user->last_active_at)->toBeNull();
});

test('user can be soft deleted', function () {
    $user = User::factory()->create();
    $userId = $user->id;

    $user->delete();

    expect(User::find($userId))->toBeNull();
    expect(User::withTrashed()->find($userId))->not->toBeNull();
    expect(User::withTrashed()->find($userId)->trashed())->toBeTrue();
});

test('user can have datacenter relationship', function () {
    $user = User::factory()->create();

    expect($user->datacenters())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    expect($user->datacenters)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('user factory creates valid user with status and last_active_at', function () {
    $user = User::factory()->create();

    expect($user->status)->toBe('active');
    expect($user->last_active_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

<?php

use App\Models\ActivityLog;
use App\Models\Datacenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('user model logs created event when new user created', function () {
    $causer = User::factory()->create();
    Auth::login($causer);

    $newUser = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'status' => 'active',
    ]);

    $activityLog = ActivityLog::where('subject_type', User::class)
        ->where('subject_id', $newUser->id)
        ->where('action', 'created')
        ->first();

    expect($activityLog)->not->toBeNull();
    expect($activityLog->causer_id)->toBe($causer->id);
    expect($activityLog->new_values)->toBeArray();
    expect($activityLog->new_values)->toHaveKey('name');
    expect($activityLog->new_values['name'])->toBe('John Doe');
    expect($activityLog->new_values)->toHaveKey('email');
    expect($activityLog->new_values['email'])->toBe('john@example.com');
    expect($activityLog->old_values)->toBeNull();
});

test('user model logs updated event with correct old and new values', function () {
    $causer = User::factory()->create();
    Auth::login($causer);

    // Create user without triggering events to set up initial state
    $user = User::withoutEvents(function () {
        return User::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);
    });

    // Update the user - this should trigger the updated event
    $user->update([
        'name' => 'Updated Name',
        'status' => 'inactive',
    ]);

    $activityLog = ActivityLog::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('action', 'updated')
        ->first();

    expect($activityLog)->not->toBeNull();
    expect($activityLog->causer_id)->toBe($causer->id);
    expect($activityLog->old_values)->toBeArray();
    expect($activityLog->new_values)->toBeArray();
    expect($activityLog->old_values)->toHaveKey('name');
    expect($activityLog->old_values['name'])->toBe('Original Name');
    expect($activityLog->new_values)->toHaveKey('name');
    expect($activityLog->new_values['name'])->toBe('Updated Name');
    expect($activityLog->old_values)->toHaveKey('status');
    expect($activityLog->old_values['status'])->toBe('active');
    expect($activityLog->new_values)->toHaveKey('status');
    expect($activityLog->new_values['status'])->toBe('inactive');
});

test('user model excludes password and sensitive fields from logged values', function () {
    $causer = User::factory()->create();
    Auth::login($causer);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret_password',
        'status' => 'active',
    ]);

    $activityLog = ActivityLog::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('action', 'created')
        ->first();

    expect($activityLog)->not->toBeNull();
    // Password and other sensitive fields should be excluded
    expect($activityLog->new_values)->not->toHaveKey('password');
    expect($activityLog->new_values)->not->toHaveKey('remember_token');
    expect($activityLog->new_values)->not->toHaveKey('two_factor_secret');
    expect($activityLog->new_values)->not->toHaveKey('two_factor_recovery_codes');
    // Non-sensitive fields should be present
    expect($activityLog->new_values)->toHaveKey('name');
    expect($activityLog->new_values)->toHaveKey('email');
});

test('datacenter model logs events correctly', function () {
    $causer = User::factory()->create();
    Auth::login($causer);

    $datacenter = Datacenter::create([
        'name' => 'Primary Data Center',
        'address_line_1' => '123 Main Street',
        'city' => 'New York',
        'state_province' => 'NY',
        'postal_code' => '10001',
        'country' => 'United States',
        'primary_contact_name' => 'John Doe',
        'primary_contact_email' => 'john@example.com',
        'primary_contact_phone' => '+1-555-123-4567',
    ]);

    $activityLog = ActivityLog::where('subject_type', Datacenter::class)
        ->where('subject_id', $datacenter->id)
        ->where('action', 'created')
        ->first();

    expect($activityLog)->not->toBeNull();
    expect($activityLog->causer_id)->toBe($causer->id);
    expect($activityLog->new_values)->toBeArray();
    expect($activityLog->new_values)->toHaveKey('name');
    expect($activityLog->new_values['name'])->toBe('Primary Data Center');

    // Update the datacenter
    $datacenter->update(['name' => 'Updated Data Center']);

    $updateLog = ActivityLog::where('subject_type', Datacenter::class)
        ->where('subject_id', $datacenter->id)
        ->where('action', 'updated')
        ->first();

    expect($updateLog)->not->toBeNull();
    expect($updateLog->old_values)->toHaveKey('name');
    expect($updateLog->old_values['name'])->toBe('Primary Data Center');
    expect($updateLog->new_values)->toHaveKey('name');
    expect($updateLog->new_values['name'])->toBe('Updated Data Center');
});

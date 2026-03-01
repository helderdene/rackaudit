<?php

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear any activity logs that might be created during setup
    ActivityLog::query()->delete();
});

test('activity:cleanup command deletes records older than specified days', function () {
    // Create activity logs with different ages
    ActivityLog::factory()->create(['created_at' => now()->subDays(100)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(40)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(20)]);
    ActivityLog::factory()->create(['created_at' => now()]);

    // Run command with 30 days retention
    $this->artisan('activity:cleanup', ['--days' => 30])
        ->expectsOutputToContain('Deleted 2 activity log')
        ->assertSuccessful();

    // Verify only records from last 30 days remain
    expect(ActivityLog::count())->toBe(2);
});

test('activity:cleanup command uses default 365 days when --days not provided', function () {
    // Create activity logs with different ages
    ActivityLog::factory()->create(['created_at' => now()->subDays(400)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(370)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(300)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(100)]);
    ActivityLog::factory()->create(['created_at' => now()]);

    // Run command without --days option (should use default 365)
    $this->artisan('activity:cleanup')
        ->expectsOutputToContain('Deleted 2 activity log')
        ->assertSuccessful();

    // Verify only records from last 365 days remain
    expect(ActivityLog::count())->toBe(3);
});

test('activity:cleanup command outputs count of deleted records', function () {
    // Create some old activity logs
    ActivityLog::factory()->count(5)->create(['created_at' => now()->subDays(500)]);
    ActivityLog::factory()->count(3)->create(['created_at' => now()]);

    // Run command and verify output
    $this->artisan('activity:cleanup')
        ->expectsOutputToContain('Deleted 5 activity log')
        ->assertSuccessful();
});

test('activity:cleanup command processes in chunks to avoid memory issues', function () {
    // Create more than one chunk worth of old activity logs
    // Using a smaller number to speed up test while still testing chunking
    ActivityLog::factory()->count(150)->create(['created_at' => now()->subDays(500)]);
    ActivityLog::factory()->count(10)->create(['created_at' => now()]);

    // Run command
    $this->artisan('activity:cleanup')
        ->expectsOutputToContain('Deleted 150 activity log')
        ->assertSuccessful();

    // Verify all old records were deleted and new ones remain
    expect(ActivityLog::count())->toBe(10);
});

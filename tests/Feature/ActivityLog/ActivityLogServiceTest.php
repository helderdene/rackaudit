<?php

use App\Models\ActivityLog;
use App\Models\Concerns\Loggable;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/**
 * Helper model class with Loggable trait for testing
 */
class TestLoggableModel extends Model
{
    use Loggable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * Fields to exclude from activity log.
     *
     * @var list<string>
     */
    protected array $excludeFromActivityLog = ['password'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}

test('loggable trait logs created event with correct values', function () {
    $causer = User::factory()->create();
    Auth::login($causer);

    // Create a model instance with Loggable trait
    $model = TestLoggableModel::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret123',
        'status' => 'active',
    ]);

    // Verify activity log was created
    $activityLog = ActivityLog::where('subject_type', TestLoggableModel::class)
        ->where('subject_id', $model->id)
        ->where('action', 'created')
        ->first();

    expect($activityLog)->not->toBeNull();
    expect($activityLog->causer_id)->toBe($causer->id);
    expect($activityLog->new_values)->toBeArray();
    expect($activityLog->new_values)->toHaveKey('name');
    expect($activityLog->new_values['name'])->toBe('Test User');
    expect($activityLog->old_values)->toBeNull();
});

test('loggable trait logs updated event with old and new values diff', function () {
    $causer = User::factory()->create();
    Auth::login($causer);

    // Create model without logging to set up initial state
    $model = TestLoggableModel::withoutEvents(function () {
        return TestLoggableModel::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);
    });

    // Update the model - this should trigger the updated event
    $model->update([
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    // Verify activity log was created for update
    $activityLog = ActivityLog::where('subject_type', TestLoggableModel::class)
        ->where('subject_id', $model->id)
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
});

test('loggable trait logs deleted event', function () {
    $causer = User::factory()->create();
    Auth::login($causer);

    // Create model without logging to set up initial state
    $model = TestLoggableModel::withoutEvents(function () {
        return TestLoggableModel::create([
            'name' => 'To Be Deleted',
            'email' => 'delete@example.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);
    });

    $modelId = $model->id;

    // Delete the model
    $model->delete();

    // Verify activity log was created for delete
    $activityLog = ActivityLog::where('subject_type', TestLoggableModel::class)
        ->where('subject_id', $modelId)
        ->where('action', 'deleted')
        ->first();

    expect($activityLog)->not->toBeNull();
    expect($activityLog->causer_id)->toBe($causer->id);
    expect($activityLog->old_values)->toBeArray();
    expect($activityLog->old_values)->toHaveKey('name');
    expect($activityLog->old_values['name'])->toBe('To Be Deleted');
    expect($activityLog->new_values)->toBeNull();
});

test('loggable trait excludes fields from excludeFromActivityLog property', function () {
    $causer = User::factory()->create();
    Auth::login($causer);

    // Create model - password should be excluded
    $model = TestLoggableModel::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret123',
        'status' => 'active',
    ]);

    $activityLog = ActivityLog::where('subject_type', TestLoggableModel::class)
        ->where('subject_id', $model->id)
        ->where('action', 'created')
        ->first();

    expect($activityLog)->not->toBeNull();
    expect($activityLog->new_values)->not->toHaveKey('password');
    expect($activityLog->new_values)->toHaveKey('name');
    expect($activityLog->new_values)->toHaveKey('email');
});

test('activity log service creates log entry with all required fields', function () {
    $causer = User::factory()->create();
    $subject = User::factory()->create();

    $service = new ActivityLogService();

    $activityLog = $service->log(
        subject: $subject,
        action: 'updated',
        oldValues: ['name' => 'Old Name'],
        newValues: ['name' => 'New Name'],
        causer: $causer
    );

    expect($activityLog)->toBeInstanceOf(ActivityLog::class);
    expect($activityLog->subject_type)->toBe(User::class);
    expect($activityLog->subject_id)->toBe($subject->id);
    expect($activityLog->causer_id)->toBe($causer->id);
    expect($activityLog->action)->toBe('updated');
    expect($activityLog->old_values)->toBe(['name' => 'Old Name']);
    expect($activityLog->new_values)->toBe(['name' => 'New Name']);
});

test('activity log service filters sensitive fields from subject model', function () {
    $causer = User::factory()->create();

    // Create a model with the Loggable trait that has excludeFromActivityLog
    $subject = TestLoggableModel::withoutEvents(function () {
        return TestLoggableModel::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);
    });

    $service = new ActivityLogService();

    $activityLog = $service->log(
        subject: $subject,
        action: 'created',
        oldValues: null,
        newValues: [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret123',
            'status' => 'active',
        ],
        causer: $causer
    );

    expect($activityLog->new_values)->not->toHaveKey('password');
    expect($activityLog->new_values)->toHaveKey('name');
    expect($activityLog->new_values)->toHaveKey('email');
});

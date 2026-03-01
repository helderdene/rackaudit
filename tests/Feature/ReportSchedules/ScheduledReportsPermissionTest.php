<?php

/**
 * Permission tests for Scheduled Reports and Distribution Lists feature.
 *
 * Tests cover:
 * - IT Manager/Administrator can create/manage all scheduled reports
 * - Operator/Auditor can create schedules within accessible datacenters
 * - Distribution list CRUD follows same role-based access
 * - Unauthorized users cannot access schedule management
 */

use App\Models\Datacenter;
use App\Models\DistributionList;
use App\Models\ReportSchedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('IT Manager and Administrator can create and manage all scheduled reports', function () {
    // Create admin and IT Manager users
    $administrator = User::factory()->create();
    $administrator->assignRole('Administrator');

    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    // Create a distribution list owned by admin
    $distributionList = DistributionList::factory()->create([
        'user_id' => $administrator->id,
    ]);

    // Create a report schedule owned by admin
    $schedule = ReportSchedule::factory()->create([
        'user_id' => $administrator->id,
        'distribution_list_id' => $distributionList->id,
    ]);

    // Test Administrator can view any schedules
    expect($administrator->can('viewAny', ReportSchedule::class))->toBeTrue();
    expect($administrator->can('view', $schedule))->toBeTrue();
    expect($administrator->can('create', ReportSchedule::class))->toBeTrue();
    expect($administrator->can('update', $schedule))->toBeTrue();
    expect($administrator->can('delete', $schedule))->toBeTrue();

    // Test IT Manager can view any schedules
    expect($itManager->can('viewAny', ReportSchedule::class))->toBeTrue();
    expect($itManager->can('view', $schedule))->toBeTrue();
    expect($itManager->can('create', ReportSchedule::class))->toBeTrue();
    expect($itManager->can('update', $schedule))->toBeTrue();
    expect($itManager->can('delete', $schedule))->toBeTrue();
});

test('Operator and Auditor can create schedules for accessible datacenters', function () {
    // Create datacenters
    $accessibleDatacenter = Datacenter::factory()->create(['name' => 'Accessible DC']);
    $inaccessibleDatacenter = Datacenter::factory()->create(['name' => 'Inaccessible DC']);

    // Create operator with access to one datacenter
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($accessibleDatacenter->id);

    // Create auditor with access to one datacenter
    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');
    $auditor->datacenters()->attach($accessibleDatacenter->id);

    // Create distribution lists
    $operatorList = DistributionList::factory()->create(['user_id' => $operator->id]);
    $auditorList = DistributionList::factory()->create(['user_id' => $auditor->id]);

    // Create schedules owned by operator and auditor
    $operatorSchedule = ReportSchedule::factory()->create([
        'user_id' => $operator->id,
        'distribution_list_id' => $operatorList->id,
    ]);

    $auditorSchedule = ReportSchedule::factory()->create([
        'user_id' => $auditor->id,
        'distribution_list_id' => $auditorList->id,
    ]);

    // Operator can view schedules and create new ones
    expect($operator->can('viewAny', ReportSchedule::class))->toBeTrue();
    expect($operator->can('view', $operatorSchedule))->toBeTrue();
    expect($operator->can('create', ReportSchedule::class))->toBeTrue();
    expect($operator->can('update', $operatorSchedule))->toBeTrue();
    expect($operator->can('delete', $operatorSchedule))->toBeTrue();

    // Auditor can view schedules and create new ones
    expect($auditor->can('viewAny', ReportSchedule::class))->toBeTrue();
    expect($auditor->can('view', $auditorSchedule))->toBeTrue();
    expect($auditor->can('create', ReportSchedule::class))->toBeTrue();
    expect($auditor->can('update', $auditorSchedule))->toBeTrue();
    expect($auditor->can('delete', $auditorSchedule))->toBeTrue();

    // Operator cannot update or delete auditor's schedule
    expect($operator->can('update', $auditorSchedule))->toBeFalse();
    expect($operator->can('delete', $auditorSchedule))->toBeFalse();

    // Auditor cannot update or delete operator's schedule
    expect($auditor->can('update', $operatorSchedule))->toBeFalse();
    expect($auditor->can('delete', $operatorSchedule))->toBeFalse();
});

test('distribution list CRUD follows same role-based access', function () {
    // Create users with different roles
    $administrator = User::factory()->create();
    $administrator->assignRole('Administrator');

    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    // Create a distribution list owned by operator
    $operatorList = DistributionList::factory()->create(['user_id' => $operator->id]);

    // Administrator can manage any distribution list
    expect($administrator->can('viewAny', DistributionList::class))->toBeTrue();
    expect($administrator->can('view', $operatorList))->toBeTrue();
    expect($administrator->can('create', DistributionList::class))->toBeTrue();
    expect($administrator->can('update', $operatorList))->toBeTrue();
    expect($administrator->can('delete', $operatorList))->toBeTrue();

    // IT Manager can manage any distribution list
    expect($itManager->can('viewAny', DistributionList::class))->toBeTrue();
    expect($itManager->can('view', $operatorList))->toBeTrue();
    expect($itManager->can('create', DistributionList::class))->toBeTrue();
    expect($itManager->can('update', $operatorList))->toBeTrue();
    expect($itManager->can('delete', $operatorList))->toBeTrue();

    // Operator can only manage their own distribution list
    expect($operator->can('viewAny', DistributionList::class))->toBeTrue();
    expect($operator->can('view', $operatorList))->toBeTrue();
    expect($operator->can('create', DistributionList::class))->toBeTrue();
    expect($operator->can('update', $operatorList))->toBeTrue();
    expect($operator->can('delete', $operatorList))->toBeTrue();

    // Viewer can only view distribution lists
    expect($viewer->can('viewAny', DistributionList::class))->toBeTrue();
    expect($viewer->can('view', $operatorList))->toBeFalse();
    expect($viewer->can('create', DistributionList::class))->toBeFalse();
    expect($viewer->can('update', $operatorList))->toBeFalse();
    expect($viewer->can('delete', $operatorList))->toBeFalse();
});

test('unauthorized users cannot access schedule management', function () {
    // Create a viewer user (lowest access level)
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    // Create another user's schedule
    $owner = User::factory()->create();
    $owner->assignRole('Auditor');

    $distributionList = DistributionList::factory()->create(['user_id' => $owner->id]);
    $schedule = ReportSchedule::factory()->create([
        'user_id' => $owner->id,
        'distribution_list_id' => $distributionList->id,
    ]);

    // Viewer can view the list of schedules
    expect($viewer->can('viewAny', ReportSchedule::class))->toBeTrue();

    // Viewer cannot view other user's schedule details
    expect($viewer->can('view', $schedule))->toBeFalse();

    // Viewer cannot create schedules
    expect($viewer->can('create', ReportSchedule::class))->toBeFalse();

    // Viewer cannot update or delete schedules
    expect($viewer->can('update', $schedule))->toBeFalse();
    expect($viewer->can('delete', $schedule))->toBeFalse();
});

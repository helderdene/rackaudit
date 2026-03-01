<?php

/**
 * Frontend tests for Distribution List UI components.
 *
 * Tests cover:
 * - Distribution list index page renders correctly
 * - Create distribution list form validation
 * - Adding/removing members in form
 * - Edit distribution list loads existing data
 * - Delete confirmation works
 */

use App\Models\DistributionList;
use App\Models\DistributionListMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');
});

test('distribution list index page renders correctly', function () {
    // Create distribution lists for the operator
    $list1 = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'Finance Team',
        'description' => 'Weekly finance report recipients',
    ]);
    DistributionListMember::factory()->count(3)->create([
        'distribution_list_id' => $list1->id,
    ]);

    $list2 = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'IT Operations',
        'description' => null,
    ]);
    DistributionListMember::factory()->count(5)->create([
        'distribution_list_id' => $list2->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('distribution-lists.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DistributionLists/Index')
            ->has('distributionLists', 2)
            ->has('distributionLists.0', fn (Assert $list) => $list
                ->has('id')
                ->has('name')
                ->has('description')
                ->has('members_count')
                ->has('created_at')
            )
            ->where('canCreate', true)
        );
});

test('create distribution list form validation works', function () {
    // Test validation: name required
    $response = $this->actingAs($this->operator)
        ->post(route('distribution-lists.store'), [
            'name' => '',
            'description' => 'Test description',
            'members' => [],
        ]);

    $response->assertSessionHasErrors(['name']);

    // Test validation: name max length
    $response = $this->actingAs($this->operator)
        ->post(route('distribution-lists.store'), [
            'name' => str_repeat('a', 256),
            'description' => 'Test description',
            'members' => [],
        ]);

    $response->assertSessionHasErrors(['name']);

    // Test validation: invalid email format
    $response = $this->actingAs($this->operator)
        ->post(route('distribution-lists.store'), [
            'name' => 'Valid Name',
            'description' => 'Test description',
            'members' => [
                ['email' => 'not-an-email'],
            ],
        ]);

    $response->assertSessionHasErrors(['members.0.email']);
});

test('adding members in form creates distribution list with members', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('distribution-lists.store'), [
            'name' => 'Test Distribution List',
            'description' => 'A test list for adding members',
            'members' => [
                ['email' => 'member1@example.com'],
                ['email' => 'member2@example.com'],
                ['email' => 'member3@example.com'],
            ],
        ]);

    $response->assertRedirect(route('distribution-lists.index'));
    $response->assertSessionHas('success');

    // Verify distribution list was created
    $distributionList = DistributionList::where('name', 'Test Distribution List')->first();
    expect($distributionList)->not->toBeNull();
    expect($distributionList->members)->toHaveCount(3);

    // Verify members have correct sort order
    $members = $distributionList->members()->orderBy('sort_order')->get();
    expect($members[0]->email)->toBe('member1@example.com');
    expect($members[0]->sort_order)->toBe(1);
    expect($members[1]->email)->toBe('member2@example.com');
    expect($members[1]->sort_order)->toBe(2);
    expect($members[2]->email)->toBe('member3@example.com');
    expect($members[2]->sort_order)->toBe(3);
});

test('edit distribution list loads existing data', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'Edit Test List',
        'description' => 'Description for edit test',
    ]);

    DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'existing1@example.com',
        'sort_order' => 1,
    ]);
    DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'existing2@example.com',
        'sort_order' => 2,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('distribution-lists.edit', $distributionList));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DistributionLists/Edit')
            ->where('distributionList.id', $distributionList->id)
            ->where('distributionList.name', 'Edit Test List')
            ->where('distributionList.description', 'Description for edit test')
            ->has('distributionList.members', 2)
            ->where('distributionList.members.0.email', 'existing1@example.com')
            ->where('distributionList.members.1.email', 'existing2@example.com')
        );
});

test('delete distribution list removes list and redirects', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'List To Delete',
    ]);

    DistributionListMember::factory()->count(2)->create([
        'distribution_list_id' => $distributionList->id,
    ]);

    $listId = $distributionList->id;

    $response = $this->actingAs($this->operator)
        ->delete(route('distribution-lists.destroy', $distributionList));

    $response->assertRedirect(route('distribution-lists.index'));
    $response->assertSessionHas('success');

    // Verify list was deleted
    $this->assertDatabaseMissing('distribution_lists', ['id' => $listId]);

    // Verify members were cascade deleted
    $this->assertDatabaseMissing('distribution_list_members', [
        'distribution_list_id' => $listId,
    ]);
});

test('empty state shown when no distribution lists exist', function () {
    $response = $this->actingAs($this->operator)
        ->get(route('distribution-lists.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DistributionLists/Index')
            ->has('distributionLists', 0)
            ->where('canCreate', true)
        );
});

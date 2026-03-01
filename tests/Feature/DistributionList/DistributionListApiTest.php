<?php

/**
 * API tests for Distribution List CRUD operations.
 *
 * Tests cover:
 * - Index returns user's distribution lists with member counts
 * - Store creates list with members and validates emails
 * - Show returns list with all members
 * - Update modifies list name/description and members
 * - Destroy removes list (cascade deletes members)
 * - Validation errors for invalid emails
 * - Authorization (user can only manage own lists)
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

    // Disable Inertia page existence check since Vue components are created in later task groups
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');
});

test('index returns users distribution lists with member counts', function () {
    // Create distribution lists with varying member counts
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
        'name' => 'IT Team',
        'description' => null,
    ]);
    DistributionListMember::factory()->count(5)->create([
        'distribution_list_id' => $list2->id,
    ]);

    // Create another user's list (should not appear)
    $otherUserList = DistributionList::factory()->withMembers(2)->create();

    $response = $this->actingAs($this->operator)
        ->get(route('distribution-lists.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DistributionLists/Index')
            ->has('distributionLists', 2)
            ->where('distributionLists.0.name', 'Finance Team')
            ->where('distributionLists.0.members_count', 3)
            ->where('distributionLists.1.name', 'IT Team')
            ->where('distributionLists.1.members_count', 5)
            ->where('canCreate', true)
        );
});

test('store creates distribution list with members and validates emails', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('distribution-lists.store'), [
            'name' => 'New Distribution List',
            'description' => 'A test distribution list',
            'members' => [
                ['email' => 'john@example.com'],
                ['email' => 'jane@example.com'],
                ['email' => 'bob@example.com'],
            ],
        ]);

    $response->assertRedirect(route('distribution-lists.index'));

    // Verify distribution list was created
    $this->assertDatabaseHas('distribution_lists', [
        'name' => 'New Distribution List',
        'description' => 'A test distribution list',
        'user_id' => $this->operator->id,
    ]);

    // Verify members were created
    $distributionList = DistributionList::where('name', 'New Distribution List')->first();
    expect($distributionList->members)->toHaveCount(3);
    expect($distributionList->members->pluck('email')->toArray())
        ->toContain('john@example.com', 'jane@example.com', 'bob@example.com');
});

test('show returns distribution list with all members', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'Show Test List',
        'description' => 'List for show test',
    ]);

    DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'member1@example.com',
        'sort_order' => 1,
    ]);
    DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'member2@example.com',
        'sort_order' => 2,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('distribution-lists.show', $distributionList));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DistributionLists/Show')
            ->where('distributionList.id', $distributionList->id)
            ->where('distributionList.name', 'Show Test List')
            ->where('distributionList.description', 'List for show test')
            ->has('distributionList.members', 2)
            ->where('distributionList.members.0.email', 'member1@example.com')
            ->where('distributionList.members.1.email', 'member2@example.com')
            ->where('canEdit', true)
            ->where('canDelete', true)
        );
});

test('update modifies list name description and members', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'Original Name',
        'description' => 'Original description',
    ]);

    DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'old@example.com',
    ]);

    $response = $this->actingAs($this->operator)
        ->put(route('distribution-lists.update', $distributionList), [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'members' => [
                ['email' => 'new1@example.com'],
                ['email' => 'new2@example.com'],
            ],
        ]);

    $response->assertRedirect(route('distribution-lists.index'));

    // Verify list was updated
    $distributionList->refresh();
    expect($distributionList->name)->toBe('Updated Name');
    expect($distributionList->description)->toBe('Updated description');

    // Verify members were synced (old removed, new added)
    expect($distributionList->members)->toHaveCount(2);
    expect($distributionList->members->pluck('email')->toArray())
        ->toEqual(['new1@example.com', 'new2@example.com']);
    $this->assertDatabaseMissing('distribution_list_members', [
        'distribution_list_id' => $distributionList->id,
        'email' => 'old@example.com',
    ]);
});

test('destroy removes distribution list and cascade deletes members', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    DistributionListMember::factory()->count(3)->create([
        'distribution_list_id' => $distributionList->id,
    ]);

    $listId = $distributionList->id;
    expect(DistributionListMember::where('distribution_list_id', $listId)->count())->toBe(3);

    $response = $this->actingAs($this->operator)
        ->delete(route('distribution-lists.destroy', $distributionList));

    $response->assertRedirect(route('distribution-lists.index'));

    // Verify list was deleted
    $this->assertDatabaseMissing('distribution_lists', ['id' => $listId]);

    // Verify members were cascade deleted
    expect(DistributionListMember::where('distribution_list_id', $listId)->count())->toBe(0);
});

test('validation errors returned for invalid emails', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('distribution-lists.store'), [
            'name' => 'Test List',
            'members' => [
                ['email' => 'valid@example.com'],
                ['email' => 'invalid-email'],
                ['email' => 'another-invalid'],
            ],
        ]);

    $response->assertSessionHasErrors([
        'members.1.email',
        'members.2.email',
    ]);
});

test('authorization prevents user from managing another users list', function () {
    // Create a list owned by another operator
    $otherOperator = User::factory()->create();
    $otherOperator->assignRole('Operator');

    $otherUsersList = DistributionList::factory()->create([
        'user_id' => $otherOperator->id,
        'name' => 'Other Users List',
    ]);

    // Operator should not be able to view another operator's list
    $response = $this->actingAs($this->operator)
        ->get(route('distribution-lists.show', $otherUsersList));
    $response->assertForbidden();

    // Operator should not be able to update another operator's list
    $response = $this->actingAs($this->operator)
        ->put(route('distribution-lists.update', $otherUsersList), [
            'name' => 'Trying to Update',
        ]);
    $response->assertForbidden();

    // Operator should not be able to delete another operator's list
    $response = $this->actingAs($this->operator)
        ->delete(route('distribution-lists.destroy', $otherUsersList));
    $response->assertForbidden();

    // Admin CAN manage any list
    $response = $this->actingAs($this->admin)
        ->get(route('distribution-lists.show', $otherUsersList));
    $response->assertOk();
});

test('admin and it manager can view all distribution lists', function () {
    // Create lists owned by different users
    $operatorList = DistributionList::factory()->withMembers(2)->create([
        'user_id' => $this->operator->id,
        'name' => 'Operator List',
    ]);

    // Admin should see all lists when viewing index
    // Note: The index endpoint should return all lists for admins
    $response = $this->actingAs($this->admin)
        ->get(route('distribution-lists.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DistributionLists/Index')
            ->has('distributionLists', 1)
            ->where('distributionLists.0.name', 'Operator List')
        );
});

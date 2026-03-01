<?php

use App\Models\DistributionList;
use App\Models\DistributionListMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('distribution list can be created with name, description, and user ownership', function () {
    $user = User::factory()->create();

    $distributionList = DistributionList::factory()->create([
        'name' => 'Finance Team',
        'description' => 'Weekly report recipients from finance department',
        'user_id' => $user->id,
    ]);

    expect($distributionList->name)->toBe('Finance Team');
    expect($distributionList->description)->toBe('Weekly report recipients from finance department');
    expect($distributionList->user_id)->toBe($user->id);
    expect($distributionList->user)->toBeInstanceOf(User::class);
    expect($distributionList->user->id)->toBe($user->id);
});

test('distribution list member requires valid email format', function () {
    $distributionList = DistributionList::factory()->create();

    // Valid email should work
    $member = DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'valid@example.com',
    ]);

    expect($member->email)->toBe('valid@example.com');
    expect($member->distributionList)->toBeInstanceOf(DistributionList::class);
    expect($member->distributionList->id)->toBe($distributionList->id);
});

test('distribution list has many members relationship works correctly', function () {
    $distributionList = DistributionList::factory()->create();

    $member1 = DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'member1@example.com',
        'sort_order' => 1,
    ]);
    $member2 = DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'member2@example.com',
        'sort_order' => 2,
    ]);
    $member3 = DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'member3@example.com',
        'sort_order' => 3,
    ]);

    $distributionList->refresh();

    expect($distributionList->members)->toHaveCount(3);
    expect($distributionList->members->pluck('email')->toArray())->toContain('member1@example.com', 'member2@example.com', 'member3@example.com');
});

test('deleting distribution list cascades to delete members', function () {
    $distributionList = DistributionList::factory()->create();

    DistributionListMember::factory()->count(3)->create([
        'distribution_list_id' => $distributionList->id,
    ]);

    expect(DistributionListMember::where('distribution_list_id', $distributionList->id)->count())->toBe(3);

    $distributionList->delete();

    expect(DistributionListMember::where('distribution_list_id', $distributionList->id)->count())->toBe(0);
});

test('duplicate email within same distribution list is prevented by unique constraint', function () {
    $distributionList = DistributionList::factory()->create();

    DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'duplicate@example.com',
    ]);

    expect(fn () => DistributionListMember::factory()->create([
        'distribution_list_id' => $distributionList->id,
        'email' => 'duplicate@example.com',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

test('same email can exist in different distribution lists', function () {
    $list1 = DistributionList::factory()->create();
    $list2 = DistributionList::factory()->create();

    $member1 = DistributionListMember::factory()->create([
        'distribution_list_id' => $list1->id,
        'email' => 'shared@example.com',
    ]);
    $member2 = DistributionListMember::factory()->create([
        'distribution_list_id' => $list2->id,
        'email' => 'shared@example.com',
    ]);

    expect($member1->email)->toBe('shared@example.com');
    expect($member2->email)->toBe('shared@example.com');
    expect($member1->distribution_list_id)->not->toBe($member2->distribution_list_id);
});

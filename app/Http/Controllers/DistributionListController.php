<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDistributionListRequest;
use App\Http\Requests\UpdateDistributionListRequest;
use App\Http\Resources\DistributionListResource;
use App\Models\DistributionList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for managing distribution lists.
 *
 * Distribution lists are named groups of email recipients used for
 * scheduling report deliveries. Each list belongs to a user and
 * contains multiple email addresses as members.
 */
class DistributionListController extends Controller
{
    /**
     * Roles that have full access to all distribution lists.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Display a paginated list of distribution lists.
     * Admins/IT Managers see all lists; others see only their own.
     */
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('viewAny', DistributionList::class);

        $user = $request->user();
        $query = DistributionList::query()
            ->withCount('members');

        // Filter by user ownership unless admin/IT Manager
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $query->where('user_id', $user->id);
        }

        $distributionLists = $query->orderBy('name')
            ->get()
            ->map(fn (DistributionList $list) => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'members_count' => $list->members_count,
                'created_at' => $list->created_at,
            ]);

        return Inertia::render('DistributionLists/Index', [
            'distributionLists' => $distributionLists,
            'canCreate' => $user->can('create', DistributionList::class),
        ]);
    }

    /**
     * Show the form for creating a new distribution list.
     */
    public function create(): InertiaResponse
    {
        Gate::authorize('create', DistributionList::class);

        return Inertia::render('DistributionLists/Create');
    }

    /**
     * Store a newly created distribution list with members.
     */
    public function store(StoreDistributionListRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            $distributionList = DistributionList::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            // Create members if provided
            if (! empty($validated['members'])) {
                $this->syncMembers($distributionList, $validated['members']);
            }
        });

        return redirect()->route('distribution-lists.index')
            ->with('success', 'Distribution list created successfully.');
    }

    /**
     * Display the specified distribution list with all members.
     */
    public function show(DistributionList $distributionList): InertiaResponse
    {
        Gate::authorize('view', $distributionList);

        $distributionList->load(['members' => fn ($query) => $query->orderBy('sort_order')]);

        $user = request()->user();

        return Inertia::render('DistributionLists/Show', [
            'distributionList' => (new DistributionListResource($distributionList))->resolve(),
            'canEdit' => $user->can('update', $distributionList),
            'canDelete' => $user->can('delete', $distributionList),
        ]);
    }

    /**
     * Show the form for editing the specified distribution list.
     */
    public function edit(DistributionList $distributionList): InertiaResponse
    {
        Gate::authorize('update', $distributionList);

        $distributionList->load(['members' => fn ($query) => $query->orderBy('sort_order')]);

        return Inertia::render('DistributionLists/Edit', [
            'distributionList' => (new DistributionListResource($distributionList))->resolve(),
        ]);
    }

    /**
     * Update the specified distribution list and sync members.
     */
    public function update(UpdateDistributionListRequest $request, DistributionList $distributionList): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $distributionList) {
            $distributionList->update([
                'name' => $validated['name'] ?? $distributionList->name,
                'description' => $validated['description'] ?? null,
            ]);

            // Sync members if provided (replace all existing members)
            if (array_key_exists('members', $validated)) {
                $this->syncMembers($distributionList, $validated['members'] ?? []);
            }
        });

        return redirect()->route('distribution-lists.index')
            ->with('success', 'Distribution list updated successfully.');
    }

    /**
     * Remove the specified distribution list.
     * Members are cascade deleted via database foreign key constraint.
     */
    public function destroy(DistributionList $distributionList): RedirectResponse
    {
        Gate::authorize('delete', $distributionList);

        $distributionList->delete();

        return redirect()->route('distribution-lists.index')
            ->with('success', 'Distribution list deleted successfully.');
    }

    /**
     * Sync members for a distribution list.
     * Deletes existing members and creates new ones with proper sort order.
     *
     * @param  array<int, array{email: string}>  $members
     */
    private function syncMembers(DistributionList $distributionList, array $members): void
    {
        // Delete existing members
        $distributionList->members()->delete();

        // Create new members with sort order
        foreach ($members as $index => $member) {
            $distributionList->members()->create([
                'email' => $member['email'],
                'sort_order' => $index + 1,
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Events\UserManagement\UserCreated;
use App\Events\UserManagement\UserDeleted;
use App\Events\UserManagement\UserRoleChanged;
use App\Events\UserManagement\UserStatusChanged;
use App\Events\UserManagement\UserUpdated;
use App\Http\Requests\BulkUserStatusRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Datacenter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * The five predefined roles in the system.
     *
     * @var array<string>
     */
    private const AVAILABLE_ROLES = [
        'Administrator',
        'IT Manager',
        'Operator',
        'Auditor',
        'Viewer',
    ];

    /**
     * Display a paginated list of users with search and filters.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $query = User::with('roles');

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by role
        if ($role = $request->input('role')) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        $users = $query->orderBy('name')
            ->paginate(15)
            ->through(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'No Role',
                    'status' => $user->status,
                    'last_active_at' => $user->last_active_at,
                ];
            });

        $roles = Role::pluck('name');

        // Return JSON for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'users' => $users,
                'availableRoles' => $roles,
            ]);
        }

        return Inertia::render('Users/Index', [
            'users' => $users,
            'availableRoles' => $roles,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'role' => $request->input('role', ''),
            ],
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): InertiaResponse
    {
        $datacenters = Datacenter::select('id', 'name')->get();

        return Inertia::render('Users/Create', [
            'availableRoles' => self::AVAILABLE_ROLES,
            'datacenters' => $datacenters,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
        ]);

        // Assign role
        $user->syncRoles([$validated['role']]);

        // Sync datacenters if provided
        if (! empty($validated['datacenter_ids'])) {
            $user->datacenters()->sync($validated['datacenter_ids']);
        }

        // Dispatch UserCreated event
        UserCreated::dispatch($user, $request->user(), now());

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user): InertiaResponse
    {
        $datacenters = Datacenter::select('id', 'name')->get();

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? '',
                'status' => $user->status,
                'datacenter_ids' => $user->datacenters->pluck('id')->toArray(),
            ],
            'availableRoles' => self::AVAILABLE_ROLES,
            'datacenters' => $datacenters,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $request->user();

        // Capture old values for event dispatching
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
        ];
        $oldRole = $user->roles->first()?->name;

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => $validated['status'],
        ];

        // Only update password if provided
        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Sync role
        $user->syncRoles([$validated['role']]);

        // Sync datacenters
        $datacenterIds = $validated['datacenter_ids'] ?? [];
        $user->datacenters()->sync($datacenterIds);

        // Capture new values for event
        $newValues = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => $validated['status'],
        ];

        // Dispatch UserUpdated event
        UserUpdated::dispatch($user, $actor, $oldValues, $newValues, now());

        // Dispatch UserStatusChanged event if status changed
        if ($oldValues['status'] !== $validated['status']) {
            UserStatusChanged::dispatch(
                $user,
                $actor,
                $oldValues['status'],
                $validated['status'],
                now()
            );
        }

        // Dispatch UserRoleChanged event if role changed
        if ($oldRole !== $validated['role']) {
            UserRoleChanged::dispatch($user, $actor, $oldRole, $validated['role'], now());
        }

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Soft delete the specified user.
     */
    public function destroy(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $currentUser = $request->user();

        // Prevent self-deletion
        if ($currentUser->id === $user->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You cannot delete your own account.',
                ], 403);
            }

            return redirect()->back()
                ->with('error', 'You cannot delete your own account.');
        }

        // Capture user data before deletion for the event
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
            'status' => $user->status,
        ];

        $user->delete();

        // Dispatch UserDeleted event
        UserDeleted::dispatch($userData, $currentUser, now());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User deleted successfully.',
            ]);
        }

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Bulk update status for multiple users.
     */
    public function bulkStatus(BulkUserStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $actor = $request->user();

        $users = User::whereIn('id', $validated['user_ids'])->get();

        foreach ($users as $user) {
            $oldStatus = $user->status;
            $user->update(['status' => $validated['status']]);

            // Dispatch UserStatusChanged event for each user
            UserStatusChanged::dispatch($user, $actor, $oldStatus, $validated['status'], now());
        }

        return response()->json([
            'message' => 'User statuses updated successfully.',
            'updated_count' => $users->count(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleAssignmentRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Permission\Models\Role;

class RoleAssignmentController extends Controller
{
    /**
     * Display a list of all users with their roles.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $users = User::with('roles')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'No Role',
                ];
            });

        $roles = Role::all()->pluck('name');

        // Return JSON for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'users' => $users,
                'availableRoles' => $roles,
            ]);
        }

        return Inertia::render('Users/Roles', [
            'users' => $users,
            'availableRoles' => $roles,
        ]);
    }

    /**
     * Update the role assignment for a specific user.
     */
    public function update(RoleAssignmentRequest $request, User $user): JsonResponse
    {
        $newRole = $request->validated()['role'];
        $currentUser = $request->user();

        // Prevent Administrator from removing their own Administrator role
        if ($currentUser->id === $user->id && $currentUser->hasRole('Administrator') && $newRole !== 'Administrator') {
            return response()->json([
                'message' => 'You cannot remove your own Administrator role.',
            ], 403);
        }

        // Sync to the single role (remove old role, assign new one)
        $user->syncRoles([$newRole]);

        return response()->json([
            'message' => "User role updated successfully to {$newRole}.",
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $newRole,
            ],
        ]);
    }
}

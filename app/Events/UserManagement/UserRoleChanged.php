<?php

namespace App\Events\UserManagement;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRoleChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  User  $user  The user whose role changed
     * @param  User|null  $actor  The user who made the change
     * @param  string|null  $oldRole  The previous role name
     * @param  string  $newRole  The new role name
     * @param  \DateTimeInterface  $timestamp  When the role change occurred
     */
    public function __construct(
        public User $user,
        public ?User $actor,
        public ?string $oldRole,
        public string $newRole,
        public \DateTimeInterface $timestamp
    ) {}
}

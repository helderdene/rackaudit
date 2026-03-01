<?php

namespace App\Events\UserManagement;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  User  $user  The user whose status changed
     * @param  User|null  $actor  The user who made the change
     * @param  string  $oldStatus  The previous status (active, inactive, suspended)
     * @param  string  $newStatus  The new status (active, inactive, suspended)
     * @param  \DateTimeInterface  $timestamp  When the status change occurred
     */
    public function __construct(
        public User $user,
        public ?User $actor,
        public string $oldStatus,
        public string $newStatus,
        public \DateTimeInterface $timestamp
    ) {}
}

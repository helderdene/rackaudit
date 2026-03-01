<?php

namespace App\Events\UserManagement;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  User  $user  The newly created user
     * @param  User|null  $actor  The user who created this account
     * @param  \DateTimeInterface  $timestamp  When the user was created
     */
    public function __construct(
        public User $user,
        public ?User $actor,
        public \DateTimeInterface $timestamp
    ) {}
}

<?php

namespace App\Events\UserManagement;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  User  $user  The updated user
     * @param  User|null  $actor  The user who made the update
     * @param  array<string, mixed>  $oldValues  The old values before update
     * @param  array<string, mixed>  $newValues  The new values after update
     * @param  \DateTimeInterface  $timestamp  When the update occurred
     */
    public function __construct(
        public User $user,
        public ?User $actor,
        public array $oldValues,
        public array $newValues,
        public \DateTimeInterface $timestamp
    ) {}
}

<?php

namespace App\Events\UserManagement;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $userData  The deleted user's data (since user may be soft-deleted)
     * @param  User|null  $actor  The user who performed the deletion
     * @param  \DateTimeInterface  $timestamp  When the deletion occurred
     */
    public function __construct(
        public array $userData,
        public ?User $actor,
        public \DateTimeInterface $timestamp
    ) {}
}

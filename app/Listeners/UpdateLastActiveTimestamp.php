<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateLastActiveTimestamp
{
    /**
     * Handle the Login event.
     * Updates the user's last_active_at timestamp to the current time.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        $user->update([
            'last_active_at' => now(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferencesController extends Controller
{
    /**
     * Show the user's notification preferences settings page.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        // Get current preferences or use defaults
        $preferences = $user->notification_preferences ?? User::getDefaultNotificationPreferences();

        // Ensure all categories are present (in case of partial data)
        $preferences = array_merge(User::getDefaultNotificationPreferences(), $preferences);

        return Inertia::render('settings/Notifications', [
            'preferences' => $preferences,
        ]);
    }

    /**
     * Update the user's notification preferences.
     */
    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $request->user()->update([
            'notification_preferences' => $request->validated(),
        ]);

        return to_route('notifications.edit');
    }
}

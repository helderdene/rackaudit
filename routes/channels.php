<?php

use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Private channel for user-specific notifications.
 *
 * Authorizes access only for the user who owns the channel,
 * enabling secure real-time notification delivery.
 */
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    // Only allow users to access their own notification channel
    return $user->id === $userId;
});

/**
 * Private channel for audit execution real-time updates.
 *
 * Authorizes access for audit creators and assignees to receive
 * live updates about connection verifications and locks.
 */
Broadcast::channel('audit.{auditId}', function (User $user, int $auditId) {
    $audit = Audit::find($auditId);

    if (! $audit) {
        return false;
    }

    // Allow access if user is the audit creator
    if ($audit->created_by === $user->id) {
        return true;
    }

    // Allow access if user is an assignee of the audit
    return $audit->assignees()->where('user_id', $user->id)->exists();
});

/**
 * Private channel for datacenter-scoped real-time updates.
 *
 * Authorizes access for users who have been granted access to the datacenter
 * via the user-datacenter pivot relationship. All infrastructure broadcasts
 * (connections, devices, racks, etc.) are scoped to datacenter channels.
 */
Broadcast::channel('datacenter.{datacenterId}', function (User $user, int $datacenterId) {
    $datacenter = Datacenter::find($datacenterId);

    if (! $datacenter) {
        return false;
    }

    // Allow access if user has been assigned to this datacenter
    return $user->datacenters()->where('datacenter_id', $datacenterId)->exists();
});

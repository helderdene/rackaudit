<?php

namespace App\Observers;

use App\Events\DeviceChanged;
use App\Models\Device;
use Illuminate\Support\Facades\Auth;

/**
 * Observer for Device model events.
 *
 * Dispatches DeviceChanged broadcast events when devices are created,
 * updated, or deleted to enable real-time UI updates.
 */
class DeviceObserver
{
    /**
     * Handle the Device "created" event.
     *
     * Broadcasts a 'placed' action if the device has a rack assignment,
     * otherwise no broadcast is needed for inventory-only devices.
     */
    public function created(Device $device): void
    {
        // Only broadcast if device is placed in a rack (has rack_id)
        if ($device->rack_id !== null) {
            $user = Auth::user();
            DeviceChanged::dispatch($device, 'placed', $user);
        }
    }

    /**
     * Handle the Device "updated" event.
     *
     * Determines the appropriate action based on what changed:
     * - 'placed' when rack_id changed from null to a value
     * - 'removed' when rack_id changed from a value to null
     * - 'moved' when rack_id changed from one value to another
     * - 'status_changed' when lifecycle_status changed
     */
    public function updated(Device $device): void
    {
        $user = Auth::user();
        $action = $this->determineAction($device);

        if ($action !== null) {
            DeviceChanged::dispatch($device, $action, $user);
        }
    }

    /**
     * Handle the Device "deleted" event.
     *
     * Broadcasts a 'removed' action if the device was placed in a rack.
     */
    public function deleted(Device $device): void
    {
        // Only broadcast if device was in a rack
        if ($device->rack_id !== null) {
            $user = Auth::user();
            DeviceChanged::dispatch($device, 'removed', $user);
        }
    }

    /**
     * Determine the action type based on what changed in the device.
     */
    private function determineAction(Device $device): ?string
    {
        $originalRackId = $device->getOriginal('rack_id');
        $currentRackId = $device->rack_id;

        // Check if rack_id changed
        if ($originalRackId !== $currentRackId) {
            if ($originalRackId === null && $currentRackId !== null) {
                return 'placed';
            }

            if ($originalRackId !== null && $currentRackId === null) {
                return 'removed';
            }

            if ($originalRackId !== null && $currentRackId !== null) {
                return 'moved';
            }
        }

        // Check if lifecycle_status changed
        if ($device->isDirty('lifecycle_status')) {
            return 'status_changed';
        }

        // Check if start_u or rack_face changed (repositioning within same rack)
        if ($device->isDirty('start_u') || $device->isDirty('rack_face')) {
            return 'moved';
        }

        return null;
    }
}

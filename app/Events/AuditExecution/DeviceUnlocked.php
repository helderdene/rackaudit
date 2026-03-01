<?php

namespace App\Events\AuditExecution;

use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a device is unlocked.
 *
 * Enables real-time updates for multi-operator inventory audit execution by notifying
 * other users when a device lock is released.
 */
class DeviceUnlocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AuditDeviceVerification $verification,
        public Audit $audit,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('audit.'.$this->audit->id);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'verification_id' => $this->verification->id,
            'device_id' => $this->verification->device_id,
            'audit_id' => $this->audit->id,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'device.unlocked';
    }
}

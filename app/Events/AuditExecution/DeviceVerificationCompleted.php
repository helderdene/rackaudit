<?php

namespace App\Events\AuditExecution;

use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a device verification is marked as verified, not found, or discrepant.
 *
 * Enables real-time updates for multi-operator inventory audit execution by notifying
 * other users when a device verification is completed.
 */
class DeviceVerificationCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AuditDeviceVerification $verification,
        public Audit $audit,
        public User $user,
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
        // Reload relationships for the response
        $this->verification->load(['device', 'rack', 'verifiedBy']);

        return [
            'verification_id' => $this->verification->id,
            'device_id' => $this->verification->device_id,
            'device_name' => $this->verification->device?->name,
            'rack_id' => $this->verification->rack_id,
            'verification_status' => $this->verification->verification_status->value,
            'verification_status_label' => $this->verification->verification_status->label(),
            'notes' => $this->verification->notes,
            'verified_by' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'verified_at' => $this->verification->verified_at?->toIso8601String(),
            'audit_id' => $this->audit->id,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'device.verified';
    }
}

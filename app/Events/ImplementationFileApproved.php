<?php

namespace App\Events;

use App\Models\ImplementationFile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when an implementation file's approval_status changes to 'approved'.
 *
 * Triggers full discrepancy detection for all expected connections in the file
 * since the file now serves as an authoritative source for audit comparisons.
 *
 * Implements ShouldBroadcast to notify connected users in real-time about
 * file approvals within their datacenter scope.
 */
class ImplementationFileApproved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  ImplementationFile  $implementationFile  The implementation file that was approved
     */
    public function __construct(
        public ImplementationFile $implementationFile
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('datacenter.'.$this->implementationFile->datacenter_id);
    }

    /**
     * Get the data to broadcast.
     *
     * Returns a minimal, serializable payload containing essential information
     * about the file approval for real-time UI updates.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Load approver relationship if not already loaded
        $approver = $this->implementationFile->approver;

        return [
            'file_id' => $this->implementationFile->id,
            'file_name' => $this->implementationFile->original_name,
            'approver' => $approver ? [
                'id' => $approver->id,
                'name' => $approver->name,
            ] : null,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'implementation_file.approved';
    }
}

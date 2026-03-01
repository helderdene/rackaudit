<?php

namespace App\Jobs;

use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Room;
use App\Services\DiscrepancyDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job for detecting discrepancies between expected and actual connections.
 *
 * Accepts scope parameters to determine what connections to check:
 * - datacenter_id: Detect for entire datacenter
 * - room_id: Detect for specific room
 * - implementation_file_id: Detect for specific implementation file
 * - connection_id: Detect for specific actual connection
 * - expected_connection_id: Detect for specific expected connection
 *
 * Uses the DiscrepancyDetectionService to perform actual detection and
 * persist discrepancy records. After detection completes, dispatches
 * notifications to appropriate users.
 */
class DetectDiscrepanciesJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 300;

    /**
     * Whether to dispatch notifications after detection.
     *
     * @var bool
     */
    public bool $shouldNotify;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $datacenterId  Detect for entire datacenter
     * @param  int|null  $roomId  Detect for specific room
     * @param  int|null  $implementationFileId  Detect for specific implementation file
     * @param  int|null  $connectionId  Detect for specific actual connection
     * @param  int|null  $expectedConnectionId  Detect for specific expected connection
     * @param  bool  $shouldNotify  Whether to dispatch notifications after detection
     */
    public function __construct(
        public ?int $datacenterId = null,
        public ?int $roomId = null,
        public ?int $implementationFileId = null,
        public ?int $connectionId = null,
        public ?int $expectedConnectionId = null,
        bool $shouldNotify = true
    ) {
        $this->shouldNotify = $shouldNotify;
        $this->onQueue('discrepancies');
    }

    /**
     * Execute the job.
     */
    public function handle(DiscrepancyDetectionService $detectionService): void
    {
        Log::info('DetectDiscrepanciesJob started', [
            'datacenter_id' => $this->datacenterId,
            'room_id' => $this->roomId,
            'implementation_file_id' => $this->implementationFileId,
            'connection_id' => $this->connectionId,
            'expected_connection_id' => $this->expectedConnectionId,
            'should_notify' => $this->shouldNotify,
        ]);

        try {
            $discrepancies = $this->runDetection($detectionService);

            Log::info('DetectDiscrepanciesJob completed', [
                'discrepancies_count' => $discrepancies->count(),
            ]);

            // Dispatch notifications if enabled and there are discrepancies
            if ($this->shouldNotify && $discrepancies->isNotEmpty()) {
                $this->dispatchNotifications($discrepancies);
            }
        } catch (\Exception $e) {
            Log::error('DetectDiscrepanciesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Run detection based on the configured scope.
     *
     * @return Collection<int, \App\Models\Discrepancy>
     */
    protected function runDetection(DiscrepancyDetectionService $detectionService): Collection
    {
        // Implementation file scope
        if ($this->implementationFileId !== null) {
            $implementationFile = ImplementationFile::findOrFail($this->implementationFileId);

            return $detectionService->detectForImplementationFile($implementationFile);
        }

        // Room scope
        if ($this->roomId !== null) {
            $room = Room::findOrFail($this->roomId);

            return $detectionService->detectForRoom($room);
        }

        // Datacenter scope
        if ($this->datacenterId !== null) {
            $datacenter = Datacenter::findOrFail($this->datacenterId);

            return $detectionService->detectForDatacenter($datacenter);
        }

        // Connection scope - derive datacenter from connection's ports
        if ($this->connectionId !== null) {
            $connection = Connection::with([
                'sourcePort.device.rack.row.room.datacenter',
            ])->findOrFail($this->connectionId);

            $datacenter = $connection->sourcePort?->device?->rack?->row?->room?->datacenter;

            if ($datacenter) {
                return $detectionService->detectForDatacenter($datacenter);
            }

            return collect();
        }

        // Expected connection scope - derive datacenter from implementation file
        if ($this->expectedConnectionId !== null) {
            $expectedConnection = ExpectedConnection::with([
                'implementationFile.datacenter',
            ])->findOrFail($this->expectedConnectionId);

            $datacenter = $expectedConnection->implementationFile?->datacenter;

            if ($datacenter) {
                return $detectionService->detectForDatacenter($datacenter);
            }

            return collect();
        }

        // No scope specified - do nothing
        Log::warning('DetectDiscrepanciesJob: No scope specified, skipping detection');

        return collect();
    }

    /**
     * Dispatch notifications for the detected discrepancies.
     *
     * @param  Collection<int, \App\Models\Discrepancy>  $discrepancies
     */
    protected function dispatchNotifications(Collection $discrepancies): void
    {
        $datacenterId = $this->getResolvedDatacenterId($discrepancies);

        Log::info('DetectDiscrepanciesJob: Dispatching notifications', [
            'discrepancy_count' => $discrepancies->count(),
            'datacenter_id' => $datacenterId,
        ]);

        NotifyUsersOfDiscrepancies::dispatch($discrepancies, $datacenterId);
    }

    /**
     * Get the datacenter ID for notification filtering.
     *
     * @param  Collection<int, \App\Models\Discrepancy>  $discrepancies
     */
    protected function getResolvedDatacenterId(Collection $discrepancies): ?int
    {
        // Use explicitly provided datacenter ID if available
        if ($this->datacenterId !== null) {
            return $this->datacenterId;
        }

        // Try to get datacenter from room
        if ($this->roomId !== null) {
            $room = Room::find($this->roomId);

            return $room?->datacenter_id;
        }

        // Try to get datacenter from implementation file
        if ($this->implementationFileId !== null) {
            $implementationFile = ImplementationFile::find($this->implementationFileId);

            return $implementationFile?->datacenter_id;
        }

        // Try to get datacenter from the first discrepancy
        return $discrepancies->first()?->datacenter_id;
    }
}

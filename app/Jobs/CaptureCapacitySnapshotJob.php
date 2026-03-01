<?php

namespace App\Jobs;

use App\Models\CapacitySnapshot;
use App\Models\Datacenter;
use App\Models\Device;
use App\Services\CapacityCalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job for capturing daily capacity snapshots for all datacenters.
 *
 * Calculates and stores point-in-time capacity metrics including
 * U-space utilization, power consumption, port statistics, and device counts
 * for historical trend analysis.
 */
class CaptureCapacitySnapshotJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Execute the job.
     */
    public function handle(CapacityCalculationService $calculationService): void
    {
        $datacenters = Datacenter::all();
        $snapshotDate = now()->toDateString();
        $capturedCount = 0;

        foreach ($datacenters as $datacenter) {
            try {
                $metrics = $calculationService->getCapacityMetrics(
                    $datacenter->id,
                    null,
                    null
                );

                // Calculate device count for this datacenter
                $deviceCount = $this->getDeviceCountForDatacenter($datacenter->id);

                CapacitySnapshot::updateOrCreate(
                    [
                        'datacenter_id' => $datacenter->id,
                        'snapshot_date' => $snapshotDate,
                    ],
                    [
                        'rack_utilization_percent' => $metrics['u_space']['utilization_percent'],
                        'power_utilization_percent' => $metrics['power']['utilization_percent'],
                        'total_u_space' => $metrics['u_space']['total_u_space'],
                        'used_u_space' => $metrics['u_space']['used_u_space'],
                        'total_power_capacity' => $metrics['power']['total_capacity'],
                        'total_power_consumption' => $metrics['power']['total_consumption'],
                        'port_stats' => $metrics['port_capacity'],
                        'device_count' => $deviceCount,
                    ]
                );

                $capturedCount++;
            } catch (\Exception $e) {
                Log::error('CaptureCapacitySnapshotJob: Failed to capture snapshot for datacenter', [
                    'datacenter_id' => $datacenter->id,
                    'datacenter_name' => $datacenter->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('CaptureCapacitySnapshotJob: Completed capacity snapshots', [
            'snapshot_date' => $snapshotDate,
            'datacenters_captured' => $capturedCount,
            'total_datacenters' => $datacenters->count(),
        ]);
    }

    /**
     * Calculate the total device count for a datacenter.
     *
     * Counts all devices placed in racks within the datacenter's room/row hierarchy.
     */
    private function getDeviceCountForDatacenter(int $datacenterId): int
    {
        return Device::query()
            ->whereHas('rack.row.room', function (Builder $q) use ($datacenterId) {
                $q->where('datacenter_id', $datacenterId);
            })
            ->count();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CaptureCapacitySnapshotJob: Job failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}

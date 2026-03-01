<?php

namespace App\Jobs;

use App\Models\Datacenter;
use App\Services\PersistentDiscrepancyFindingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job for promoting persistent discrepancies into findings.
 *
 * Processes a specific datacenter (or all datacenters) to find Open discrepancies
 * that have persisted beyond the configured threshold and auto-creates findings
 * for them. Dispatches notifications when findings are created.
 */
class PromotePersistentDiscrepanciesJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $datacenterId  Optional datacenter ID to scope processing
     */
    public function __construct(
        public ?int $datacenterId = null
    ) {
        $this->onQueue('discrepancies');
    }

    /**
     * Execute the job.
     */
    public function handle(PersistentDiscrepancyFindingService $service): void
    {
        Log::info('PromotePersistentDiscrepanciesJob started', [
            'datacenter_id' => $this->datacenterId,
        ]);

        try {
            $datacenters = $this->datacenterId
                ? Datacenter::where('id', $this->datacenterId)->get()
                : Datacenter::all();

            $allFindings = collect();

            foreach ($datacenters as $datacenter) {
                $findings = $service->processDatacenter($datacenter);
                $allFindings = $allFindings->merge($findings);
            }

            Log::info('PromotePersistentDiscrepanciesJob completed', [
                'total_findings_created' => $allFindings->count(),
            ]);

            if ($allFindings->isNotEmpty()) {
                NotifyUsersOfPersistentDiscrepancyFindings::dispatch(
                    $allFindings,
                    $this->datacenterId
                );
            }
        } catch (\Exception $e) {
            Log::error('PromotePersistentDiscrepanciesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}

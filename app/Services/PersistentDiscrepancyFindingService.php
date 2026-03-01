<?php

namespace App\Services;

use App\Enums\DiscrepancyType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Datacenter;
use App\Models\Discrepancy;
use App\Models\Finding;
use App\Models\FindingCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for auto-promoting persistent discrepancies into findings.
 *
 * Discrepancies that remain Open beyond a configurable threshold (default 3 days)
 * are automatically promoted to Findings to ensure they get tracked and assigned.
 */
class PersistentDiscrepancyFindingService
{
    /**
     * Process a datacenter and create findings for persistent discrepancies.
     *
     * @return Collection<int, Finding> The newly created findings
     */
    public function processDatacenter(Datacenter $datacenter): Collection
    {
        $thresholdDays = config('discrepancies.auto_findings.persistence_threshold_days', 3);
        $cutoff = now()->subDays($thresholdDays);

        $discrepancies = Discrepancy::query()
            ->persistentBeyond($cutoff)
            ->forDatacenter($datacenter->id)
            ->get();

        if ($discrepancies->isEmpty()) {
            Log::info('PersistentDiscrepancyFindingService: No persistent discrepancies found', [
                'datacenter_id' => $datacenter->id,
                'threshold_days' => $thresholdDays,
            ]);

            return collect();
        }

        Log::info('PersistentDiscrepancyFindingService: Processing persistent discrepancies', [
            'datacenter_id' => $datacenter->id,
            'count' => $discrepancies->count(),
        ]);

        $findings = collect();

        foreach ($discrepancies as $discrepancy) {
            $finding = $this->createFindingForDiscrepancy($discrepancy);
            $findings->push($finding);
        }

        Log::info('PersistentDiscrepancyFindingService: Findings created', [
            'datacenter_id' => $datacenter->id,
            'findings_count' => $findings->count(),
        ]);

        return $findings;
    }

    /**
     * Create a Finding from a persistent Discrepancy and link them.
     */
    public function createFindingForDiscrepancy(Discrepancy $discrepancy): Finding
    {
        return DB::transaction(function () use ($discrepancy) {
            $dueDateDays = config('discrepancies.auto_findings.due_date_days', 7);

            $finding = Finding::create([
                'audit_id' => null,
                'datacenter_id' => $discrepancy->datacenter_id,
                'discrepancy_type' => $discrepancy->discrepancy_type,
                'title' => $this->generateTitle($discrepancy),
                'description' => $this->generateDescription($discrepancy),
                'status' => FindingStatus::Open,
                'severity' => $this->mapSeverity($discrepancy->discrepancy_type),
                'finding_category_id' => $this->resolveCategoryId($discrepancy->discrepancy_type),
                'due_date' => now()->addDays($dueDateDays),
            ]);

            $discrepancy->update(['finding_id' => $finding->id]);

            return $finding;
        });
    }

    /**
     * Map discrepancy type to finding severity.
     *
     * Missing and Conflicting discrepancies are High severity as they indicate
     * significant infrastructure issues. Others are Medium.
     */
    protected function mapSeverity(DiscrepancyType $type): FindingSeverity
    {
        return match ($type) {
            DiscrepancyType::Missing, DiscrepancyType::Conflicting => FindingSeverity::High,
            default => FindingSeverity::Medium,
        };
    }

    /**
     * Resolve a finding category ID from the discrepancy type.
     *
     * Default categories are seeded from DiscrepancyType values.
     */
    protected function resolveCategoryId(DiscrepancyType $type): ?int
    {
        return FindingCategory::query()
            ->defaults()
            ->where('name', $type->label())
            ->value('id');
    }

    /**
     * Generate a title for the auto-created finding.
     */
    protected function generateTitle(Discrepancy $discrepancy): string
    {
        $typeLabel = $discrepancy->discrepancy_type->label();

        if ($discrepancy->title) {
            return "Auto-Finding: {$discrepancy->title}";
        }

        return "Auto-Finding: {$typeLabel} Discrepancy #{$discrepancy->id}";
    }

    /**
     * Generate a description for the auto-created finding.
     */
    protected function generateDescription(Discrepancy $discrepancy): string
    {
        $thresholdDays = config('discrepancies.auto_findings.persistence_threshold_days', 3);
        $detectedAt = $discrepancy->detected_at?->format('Y-m-d H:i');

        return "Automatically created from persistent discrepancy #{$discrepancy->id}. "
            ."This discrepancy has remained open for more than {$thresholdDays} days since detection on {$detectedAt}.";
    }
}

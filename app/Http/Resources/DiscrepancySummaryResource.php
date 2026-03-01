<?php

namespace App\Http\Resources;

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming discrepancy summary statistics.
 *
 * Provides aggregate counts by type and by datacenter for dashboard display.
 * Expects an array of summary data as the resource.
 */
class DiscrepancySummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource['total'] ?? 0,
            'by_type' => $this->transformByType($this->resource['by_type'] ?? []),
            'by_status' => $this->transformByStatus($this->resource['by_status'] ?? []),
            'by_datacenter' => $this->resource['by_datacenter'] ?? [],
        ];
    }

    /**
     * Transform counts by type with labels.
     *
     * @param  array<string, int>  $counts
     * @return array<string, array<string, mixed>>
     */
    protected function transformByType(array $counts): array
    {
        $result = [];

        foreach (DiscrepancyType::cases() as $type) {
            // Skip 'matched' type as it's not a discrepancy
            if ($type === DiscrepancyType::Matched) {
                continue;
            }

            $result[$type->value] = [
                'count' => $counts[$type->value] ?? 0,
                'label' => $type->label(),
            ];
        }

        return $result;
    }

    /**
     * Transform counts by status with labels.
     *
     * @param  array<string, int>  $counts
     * @return array<string, array<string, mixed>>
     */
    protected function transformByStatus(array $counts): array
    {
        $result = [];

        foreach (DiscrepancyStatus::cases() as $status) {
            $result[$status->value] = [
                'count' => $counts[$status->value] ?? 0,
                'label' => $status->label(),
            ];
        }

        return $result;
    }
}

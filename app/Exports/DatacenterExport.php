<?php

namespace App\Exports;

use App\Exports\Templates\DatacenterTemplateExport;
use App\Models\Datacenter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Datacenter entities.
 *
 * Exports datacenter data with the same column structure as the
 * DatacenterTemplateExport for round-trip compatibility with imports.
 */
class DatacenterExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * Reuses headings from DatacenterTemplateExport for consistency.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return (new DatacenterTemplateExport)->headings();
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Datacenters';
    }

    /**
     * Get the query builder for datacenters.
     */
    protected function query(): Builder
    {
        $query = Datacenter::query();

        // Apply filter for specific datacenter IDs
        if (! empty($this->filters['datacenter_id'])) {
            $query->where('id', $this->filters['datacenter_id']);
        }

        return $query;
    }

    /**
     * Transform a Datacenter model to a row array.
     *
     * @param  Datacenter  $datacenter
     * @return array<mixed>
     */
    protected function transformRow($datacenter): array
    {
        return [
            $datacenter->name,
            $datacenter->address_line_1,
            $datacenter->address_line_2,
            $datacenter->city,
            $datacenter->state_province,
            $datacenter->postal_code,
            $datacenter->country,
            $datacenter->company_name,
            $datacenter->primary_contact_name,
            $datacenter->primary_contact_email,
            $datacenter->primary_contact_phone,
            $datacenter->secondary_contact_name,
            $datacenter->secondary_contact_email,
            $datacenter->secondary_contact_phone,
        ];
    }
}

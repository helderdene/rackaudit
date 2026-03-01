<?php

namespace App\Exports\Templates;

/**
 * Template export for Datacenter entities.
 *
 * Generates an XLSX template with headers, example data,
 * and field descriptions for bulk datacenter imports.
 */
class DatacenterTemplateExport extends AbstractTemplateExport
{
    /**
     * Get the column headers for the datacenter template.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'name',
            'address_line_1',
            'address_line_2',
            'city',
            'state_province',
            'postal_code',
            'country',
            'company_name',
            'primary_contact_name',
            'primary_contact_email',
            'primary_contact_phone',
            'secondary_contact_name',
            'secondary_contact_email',
            'secondary_contact_phone',
        ];
    }

    /**
     * Get example data for the datacenter template.
     *
     * @return array<int, array<mixed>>
     */
    public function array(): array
    {
        return [
            [
                'Example Datacenter',
                '123 Main Street',
                'Suite 100',
                'New York',
                'NY',
                '10001',
                'USA',
                'Acme Corp',
                'John Smith',
                'john.smith@example.com',
                '+1-555-123-4567',
                'Jane Doe',
                'jane.doe@example.com',
                '+1-555-987-6543',
            ],
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Datacenters';
    }

    /**
     * Get required columns for visual indication.
     *
     * @return array<string>
     */
    protected function requiredColumns(): array
    {
        return [
            'name',
            'address_line_1',
            'city',
            'state_province',
            'postal_code',
            'country',
            'primary_contact_name',
            'primary_contact_email',
            'primary_contact_phone',
        ];
    }

    /**
     * Get column comments/descriptions for helper text.
     *
     * @return array<string, string>
     */
    protected function columnComments(): array
    {
        return [
            'name' => 'Required. Unique name for the datacenter.',
            'address_line_1' => 'Required. Street address of the datacenter.',
            'address_line_2' => 'Optional. Additional address information (suite, floor, etc.).',
            'city' => 'Required. City where the datacenter is located.',
            'state_province' => 'Required. State or province.',
            'postal_code' => 'Required. Postal/ZIP code.',
            'country' => 'Required. Country (e.g., USA, UK, Germany).',
            'company_name' => 'Optional. Company that owns/operates the datacenter.',
            'primary_contact_name' => 'Required. Name of the primary contact person.',
            'primary_contact_email' => 'Required. Email address of the primary contact.',
            'primary_contact_phone' => 'Required. Phone number of the primary contact.',
            'secondary_contact_name' => 'Optional. Name of a secondary/backup contact.',
            'secondary_contact_email' => 'Optional. Email of the secondary contact.',
            'secondary_contact_phone' => 'Optional. Phone of the secondary contact.',
        ];
    }
}

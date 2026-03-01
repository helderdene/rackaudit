<?php

namespace App\Imports;

use App\Models\Datacenter;

/**
 * Import class for Datacenter entities.
 *
 * Handles validation and creation of datacenters from spreadsheet data.
 * Datacenters are top-level entities and do not require parent lookups.
 */
class DatacenterImport extends AbstractEntityImport
{
    /**
     * Get the validation rules matching StoreDatacenterRequest.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            // Basic info
            'name' => ['required', 'string', 'max:255'],

            // Location fields
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state_province' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:50'],
            'country' => ['required', 'string', 'max:255'],

            // Primary contact fields
            'company_name' => ['nullable', 'string', 'max:255'],
            'primary_contact_name' => ['required', 'string', 'max:255'],
            'primary_contact_email' => ['required', 'string', 'email', 'max:255'],
            'primary_contact_phone' => ['required', 'string', 'max:50'],

            // Secondary contact fields (all optional)
            'secondary_contact_name' => ['nullable', 'string', 'max:255'],
            'secondary_contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'secondary_contact_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            // Basic info
            'name.required' => 'The datacenter name is required.',
            'name.max' => 'The datacenter name must not exceed 255 characters.',

            // Location fields
            'address_line_1.required' => 'The address line 1 is required.',
            'address_line_1.max' => 'The address line 1 must not exceed 255 characters.',
            'address_line_2.max' => 'The address line 2 must not exceed 255 characters.',
            'city.required' => 'The city is required.',
            'city.max' => 'The city must not exceed 255 characters.',
            'state_province.required' => 'The state/province is required.',
            'state_province.max' => 'The state/province must not exceed 255 characters.',
            'postal_code.required' => 'The postal code is required.',
            'postal_code.max' => 'The postal code must not exceed 50 characters.',
            'country.required' => 'The country is required.',
            'country.max' => 'The country must not exceed 255 characters.',

            // Primary contact fields
            'company_name.max' => 'The company name must not exceed 255 characters.',
            'primary_contact_name.required' => 'The primary contact name is required.',
            'primary_contact_name.max' => 'The primary contact name must not exceed 255 characters.',
            'primary_contact_email.required' => 'The primary contact email is required.',
            'primary_contact_email.email' => 'Please enter a valid email address for the primary contact.',
            'primary_contact_email.max' => 'The primary contact email must not exceed 255 characters.',
            'primary_contact_phone.required' => 'The primary contact phone is required.',
            'primary_contact_phone.max' => 'The primary contact phone must not exceed 50 characters.',

            // Secondary contact fields
            'secondary_contact_name.max' => 'The secondary contact name must not exceed 255 characters.',
            'secondary_contact_email.email' => 'Please enter a valid email address for the secondary contact.',
            'secondary_contact_email.max' => 'The secondary contact email must not exceed 255 characters.',
            'secondary_contact_phone.max' => 'The secondary contact phone must not exceed 50 characters.',
        ];
    }

    /**
     * Create the datacenter from validated data.
     *
     * @param array<string, mixed> $data
     */
    protected function createEntity(array $data, int $rowNumber): Datacenter
    {
        return Datacenter::create([
            'name' => $this->getValue($data, 'name'),
            'address_line_1' => $this->getValue($data, 'address_line_1'),
            'address_line_2' => $this->getValue($data, 'address_line_2'),
            'city' => $this->getValue($data, 'city'),
            'state_province' => $this->getValue($data, 'state_province'),
            'postal_code' => $this->getValue($data, 'postal_code'),
            'country' => $this->getValue($data, 'country'),
            'company_name' => $this->getValue($data, 'company_name'),
            'primary_contact_name' => $this->getValue($data, 'primary_contact_name'),
            'primary_contact_email' => $this->getValue($data, 'primary_contact_email'),
            'primary_contact_phone' => $this->getValue($data, 'primary_contact_phone'),
            'secondary_contact_name' => $this->getValue($data, 'secondary_contact_name'),
            'secondary_contact_email' => $this->getValue($data, 'secondary_contact_email'),
            'secondary_contact_phone' => $this->getValue($data, 'secondary_contact_phone'),
        ]);
    }
}

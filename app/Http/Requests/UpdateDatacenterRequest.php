<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDatacenterRequest extends FormRequest
{
    /**
     * Roles that can update datacenters.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can update datacenters.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
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

            // Floor plan file (optional replacement)
            'floor_plan' => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:10240'],

            // Flag to remove existing floor plan
            'remove_floor_plan' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
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

            // Floor plan file
            'floor_plan.file' => 'The floor plan must be a file.',
            'floor_plan.mimes' => 'The floor plan must be a PNG, JPG, JPEG, or PDF file.',
            'floor_plan.max' => 'The floor plan must not exceed 10 MB in size.',
        ];
    }
}

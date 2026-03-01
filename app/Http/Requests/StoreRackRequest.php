<?php

namespace App\Http\Requests;

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRackRequest extends FormRequest
{
    /**
     * Roles that can create racks.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can create racks.
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
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'integer', 'min:0'],
            'u_height' => ['required', Rule::enum(RackUHeight::class)],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(RackStatus::class)],
            'pdu_ids' => ['nullable', 'array'],
            'pdu_ids.*' => ['exists:pdus,id'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'depth' => ['nullable', 'string', 'max:100'],
            'installation_date' => ['nullable', 'date'],
            'location_notes' => ['nullable', 'string', 'max:1000'],
            'specs' => ['nullable', 'array'],
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
            'name.required' => 'The rack name is required.',
            'name.string' => 'The rack name must be a string.',
            'name.max' => 'The rack name must not exceed 255 characters.',
            'position.required' => 'The rack position is required.',
            'position.integer' => 'The rack position must be an integer.',
            'position.min' => 'The rack position must be at least 0.',
            'u_height.required' => 'The rack U-height is required.',
            'u_height.Illuminate\Validation\Rules\Enum' => 'The selected rack U-height is invalid.',
            'serial_number.string' => 'The serial number must be a string.',
            'serial_number.max' => 'The serial number must not exceed 255 characters.',
            'status.required' => 'The rack status is required.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected rack status is invalid.',
            'pdu_ids.array' => 'The PDU selection must be an array.',
            'pdu_ids.*.exists' => 'The selected PDU does not exist.',
            'manufacturer.string' => 'The manufacturer must be a string.',
            'manufacturer.max' => 'The manufacturer must not exceed 255 characters.',
            'model.string' => 'The model must be a string.',
            'model.max' => 'The model must not exceed 255 characters.',
            'depth.string' => 'The depth must be a string.',
            'depth.max' => 'The depth must not exceed 100 characters.',
            'installation_date.date' => 'The installation date must be a valid date.',
            'location_notes.string' => 'The location notes must be a string.',
            'location_notes.max' => 'The location notes must not exceed 1000 characters.',
            'specs.array' => 'The specifications must be an array.',
        ];
    }
}

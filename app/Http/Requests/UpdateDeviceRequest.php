<?php

namespace App\Http\Requests;

use App\Enums\DeviceDepth;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    /**
     * Roles that can update devices.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can update devices.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Prepare the data for validation.
     * Decodes JSON string for specs field when submitted via form.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('specs') && is_string($this->specs)) {
            $decoded = json_decode($this->specs, true);
            $this->merge([
                'specs' => is_array($decoded) ? $decoded : null,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     * Note: asset_tag is excluded as it is immutable after creation.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Required fields
            'name' => ['required', 'string', 'max:255'],
            'device_type_id' => ['required', 'exists:device_types,id'],
            'lifecycle_status' => ['required', Rule::enum(DeviceLifecycleStatus::class)],

            // Optional identification fields
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],

            // Warranty and purchase dates
            'purchase_date' => ['nullable', 'date'],
            'warranty_start_date' => ['nullable', 'date'],
            'warranty_end_date' => ['nullable', 'date', 'after_or_equal:warranty_start_date'],

            // Physical dimension fields (whole numbers 1-48)
            'u_height' => ['required', 'integer', 'min:1', 'max:48'],
            'depth' => ['required', Rule::enum(DeviceDepth::class)],
            'width_type' => ['required', Rule::enum(DeviceWidthType::class)],
            'rack_face' => ['required', Rule::enum(DeviceRackFace::class)],

            // Placement fields (nullable for unplaced inventory)
            'rack_id' => ['nullable', 'exists:racks,id'],
            'start_u' => ['nullable', 'integer', 'min:1', 'max:48'],

            // Flexible specifications (JSON)
            'specs' => ['nullable', 'array'],

            // Notes
            'notes' => ['nullable', 'string', 'max:65535'],
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
            'name.required' => 'The device name is required.',
            'name.string' => 'The device name must be a string.',
            'name.max' => 'The device name must not exceed 255 characters.',
            'device_type_id.required' => 'The device type is required.',
            'device_type_id.exists' => 'The selected device type does not exist.',
            'lifecycle_status.required' => 'The lifecycle status is required.',
            'lifecycle_status.Illuminate\Validation\Rules\Enum' => 'The selected lifecycle status is invalid.',
            'serial_number.string' => 'The serial number must be a string.',
            'serial_number.max' => 'The serial number must not exceed 255 characters.',
            'manufacturer.string' => 'The manufacturer must be a string.',
            'manufacturer.max' => 'The manufacturer must not exceed 255 characters.',
            'model.string' => 'The model must be a string.',
            'model.max' => 'The model must not exceed 255 characters.',
            'purchase_date.date' => 'The purchase date must be a valid date.',
            'warranty_start_date.date' => 'The warranty start date must be a valid date.',
            'warranty_end_date.date' => 'The warranty end date must be a valid date.',
            'warranty_end_date.after_or_equal' => 'The warranty end date must be after or equal to the warranty start date.',
            'u_height.required' => 'The U-height is required.',
            'u_height.integer' => 'The U-height must be a whole number.',
            'u_height.min' => 'The U-height must be at least 1.',
            'u_height.max' => 'The U-height must not exceed 48.',
            'depth.required' => 'The depth is required.',
            'depth.Illuminate\Validation\Rules\Enum' => 'The selected depth is invalid.',
            'width_type.required' => 'The width type is required.',
            'width_type.Illuminate\Validation\Rules\Enum' => 'The selected width type is invalid.',
            'rack_face.required' => 'The rack face is required.',
            'rack_face.Illuminate\Validation\Rules\Enum' => 'The selected rack face is invalid.',
            'rack_id.exists' => 'The selected rack does not exist.',
            'start_u.integer' => 'The start U position must be an integer.',
            'start_u.min' => 'The start U position must be at least 1.',
            'start_u.max' => 'The start U position must not exceed 48.',
            'specs.array' => 'The specifications must be an array.',
            'notes.string' => 'The notes must be a string.',
            'notes.max' => 'The notes must not exceed 65535 characters.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for bulk verifying multiple devices during inventory audit execution.
 *
 * Requires an array of verification IDs that exist in the database.
 */
class BulkVerifyDevicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization is handled by the controller/middleware.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'verification_ids' => ['required', 'array', 'min:1', 'max:100'],
            'verification_ids.*' => ['required', 'integer', 'exists:audit_device_verifications,id'],
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
            'verification_ids.required' => 'Please select at least one device to verify.',
            'verification_ids.array' => 'Invalid selection format.',
            'verification_ids.min' => 'Please select at least one device to verify.',
            'verification_ids.max' => 'Cannot bulk verify more than 100 devices at once.',
            'verification_ids.*.required' => 'Invalid verification ID.',
            'verification_ids.*.integer' => 'Verification ID must be a valid number.',
            'verification_ids.*.exists' => 'One or more selected verifications do not exist.',
        ];
    }

    /**
     * Get the verification IDs as an array of integers.
     *
     * @return array<int>
     */
    public function getVerificationIds(): array
    {
        return array_map('intval', $this->input('verification_ids', []));
    }
}

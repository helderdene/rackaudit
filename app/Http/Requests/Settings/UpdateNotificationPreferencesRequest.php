<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'audit_assignments' => ['required', 'boolean'],
            'finding_updates' => ['required', 'boolean'],
            'approval_requests' => ['required', 'boolean'],
            'discrepancies' => ['required', 'boolean'],
            'scheduled_reports' => ['required', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Check for any unexpected keys in the request
            $allowedKeys = User::NOTIFICATION_CATEGORIES;
            $providedKeys = array_keys($this->all());

            foreach ($providedKeys as $key) {
                if (! in_array($key, $allowedKeys)) {
                    $validator->errors()->add(
                        $key,
                        "The {$key} field is not a valid notification category."
                    );
                }
            }
        });
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audit_assignments.required' => 'The audit assignments preference is required.',
            'audit_assignments.boolean' => 'The audit assignments preference must be true or false.',
            'finding_updates.required' => 'The finding updates preference is required.',
            'finding_updates.boolean' => 'The finding updates preference must be true or false.',
            'approval_requests.required' => 'The approval requests preference is required.',
            'approval_requests.boolean' => 'The approval requests preference must be true or false.',
            'discrepancies.required' => 'The discrepancies preference is required.',
            'discrepancies.boolean' => 'The discrepancies preference must be true or false.',
            'scheduled_reports.required' => 'The scheduled reports preference is required.',
            'scheduled_reports.boolean' => 'The scheduled reports preference must be true or false.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\PduPhase;
use App\Enums\PduStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePduRequest extends FormRequest
{
    /**
     * Roles that can update PDUs.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can update PDUs.
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
            'model' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'total_capacity_kw' => ['nullable', 'numeric', 'min:0'],
            'voltage' => ['nullable', 'integer', 'min:0'],
            'phase' => ['required', Rule::enum(PduPhase::class)],
            'circuit_count' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::enum(PduStatus::class)],
            'row_id' => ['nullable', 'exists:rows,id'],
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
            'name.required' => 'The PDU name is required.',
            'name.string' => 'The PDU name must be a string.',
            'name.max' => 'The PDU name must not exceed 255 characters.',
            'model.string' => 'The model must be a string.',
            'model.max' => 'The model must not exceed 255 characters.',
            'manufacturer.string' => 'The manufacturer must be a string.',
            'manufacturer.max' => 'The manufacturer must not exceed 255 characters.',
            'total_capacity_kw.numeric' => 'The total capacity must be a number.',
            'total_capacity_kw.min' => 'The total capacity must be at least 0.',
            'voltage.integer' => 'The voltage must be an integer.',
            'voltage.min' => 'The voltage must be at least 0.',
            'phase.required' => 'The phase type is required.',
            'phase.Illuminate\Validation\Rules\Enum' => 'The selected phase type is invalid.',
            'circuit_count.required' => 'The circuit count is required.',
            'circuit_count.integer' => 'The circuit count must be an integer.',
            'circuit_count.min' => 'The circuit count must be at least 1.',
            'status.required' => 'The PDU status is required.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected status is invalid.',
            'row_id.exists' => 'The selected row does not exist.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Form request for approving an equipment move request.
 *
 * Approval notes are optional - the approver may provide
 * additional context or instructions for the move.
 */
class ApproveEquipmentMoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $move = $this->route('equipmentMove');

        return Gate::allows('approve', $move);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'approval_notes' => [
                'nullable',
                'string',
                'max:65535',
            ],
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
            'approval_notes.max' => 'The approval notes must not exceed 65535 characters.',
        ];
    }
}

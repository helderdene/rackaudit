<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Form request for rejecting an equipment move request.
 *
 * Rejection notes are required - the approver must provide
 * a reason for rejecting the move request.
 */
class RejectEquipmentMoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $move = $this->route('equipmentMove');

        return Gate::allows('reject', $move);
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
                'required',
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
            'approval_notes.required' => 'Please provide a reason for rejecting this move request.',
            'approval_notes.max' => 'The rejection notes must not exceed 65535 characters.',
        ];
    }
}

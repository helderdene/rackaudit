<?php

namespace App\Http\Requests;

use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRowRequest extends FormRequest
{
    /**
     * Roles that can update rows.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can update rows.
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
            'orientation' => ['required', Rule::enum(RowOrientation::class)],
            'status' => ['required', Rule::enum(RowStatus::class)],
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
            'name.required' => 'The row name is required.',
            'name.string' => 'The row name must be a string.',
            'name.max' => 'The row name must not exceed 255 characters.',
            'position.required' => 'The row position is required.',
            'position.integer' => 'The row position must be an integer.',
            'position.min' => 'The row position must be at least 0.',
            'orientation.required' => 'The row orientation is required.',
            'orientation.Illuminate\Validation\Rules\Enum' => 'The selected row orientation is invalid.',
            'status.required' => 'The row status is required.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected row status is invalid.',
        ];
    }
}

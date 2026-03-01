<?php

namespace App\Http\Requests;

use App\Enums\BulkImportEntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for validating bulk import file uploads.
 *
 * Validates the uploaded file format (CSV/XLSX), size (max 10MB),
 * and optional entity type specification.
 */
class StoreBulkImportRequest extends FormRequest
{
    /**
     * Roles that can perform bulk imports.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can perform bulk imports.
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
            'file' => [
                'required',
                'file',
                'mimes:csv,xlsx',
                'max:10240', // 10MB in kilobytes
            ],
            'entity_type' => [
                'nullable',
                Rule::enum(BulkImportEntityType::class),
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
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.mimes' => 'Only CSV and XLSX files are accepted.',
            'file.max' => 'The file size must not exceed 10MB.',
            'entity_type.Illuminate\Validation\Rules\Enum' => 'The selected entity type is invalid.',
        ];
    }
}
